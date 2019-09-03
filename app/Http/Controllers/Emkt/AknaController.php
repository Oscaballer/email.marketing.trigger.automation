<?php

namespace App\Http\Controllers\Emkt;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Ixudra\Curl\Facades\Curl;

class AknaController extends Controller
{
    public $endPoint = 'http://app.akna.com.br/emkt/int/integracao.php';

    public $storagePath = '';

    public $data;

    public $codigosIes;

    public function __construct()
    {
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $this->fileStorage = $protocol.$host."/akna_lists/";
        $this->xmlPath = storage_path().'/akna_xml';
        $this->data = include __DIR__.'/user.php';
        $this->codigosIes = include __DIR__.'/client-codes.php';
    }

    public function getXml($file)
    {
        return file_get_contents($this->xmlPath.'/'.$file.'.xml');
    }

    public function post($headers=[], $xml)
    {
        $data = $this->data;
        $data['XML'] = $xml;

        return $response = Curl::to($this->endPoint)
            ->withData($data)
            ->withHeaders($headers)
            ->post();
    }

    public function importarListaDeContatos($nome_da_lista, $nome_do_arquivo, $instituicao) 
    {
        $codigoIe = $this->codigosIes[strtoupper($instituicao)];
        $this->data['Client'] = $codigoIe;
        $url_do_arquivo = $this->fileStorage.$nome_do_arquivo;


        $xml_request = $this->getXml('listas/importar-lista-de-contatos');
        $xml_request = str_replace('[NOME DA LISTA]', $nome_da_lista, $xml_request);
        $xml_request = str_replace('[URL DO ARQUIVO]', $url_do_arquivo, $xml_request);
        $xml_request = str_replace('[NUMERO DA COLUNA EMAIL]', '2', $xml_request);

        // Separador (',', ';')
        $xml_request = str_replace('[SEPARADOR]', ';', $xml_request);

        // I = Adicionar o conteúdo do arquivo ao conteúdo da lista
        // S = Remover o conteúdo anterior da lista antes de importar
        // R = Excluir da lista os contatos importados do arquivo
        $xml_request = str_replace('[ACAO]', 'I', $xml_request);

        // S/N - Informe se o seu arquivo importado possui ou não a linha de cabeçalho
        $xml_request = str_replace('[CABECALHO]', 'S', $xml_request);

        // S/N - • Atualizar - Atualizar os dados dos contatos que estiverem diferentes.
        $xml_request = str_replace('[ATUALIZAR]', 'S', $xml_request);

        //dd($xml_request);

        $xml_response = $this->post([], $xml_request);

        $xml = new \SimpleXMLElement($xml_response);
        $codigo_do_processo = $xml->EMKT->PROCESSO[0];

        $this->consultarSituacaoDoProcesso($codigo_do_processo, $instituicao);

    }

    public function consultarSituacaoDoProcesso($codigo_do_processo, $instituicao)
    {
        $codigoIe = $this->codigosIes[strtoupper($instituicao)];
        $this->data['Client'] = $codigoIe;

        $xml_request = $this->getXml('listas/consultar-situacao-de-processo-de-importacao');
        $xml_request = str_replace('[CODIGO DO PROCESSO]', $codigo_do_processo, $xml_request);

        return $this->post([], $xml_request);

    }

}
