<?php

namespace App\Http\Controllers\Emkt;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Emkt\AknaController;
use App\Http\Controllers\PlanilhaController;
use App\Instituicao;
use App\Lista;
use App\TipoDeAcao;
use Session;

class ListaController extends Controller
{
    public $instituicoes;

    public function __construct()
    {
        $this->instituicoes = Instituicao::all();
        $this->prefixo = Instituicao::all()->pluck('prefixo', 'nome')->toArray();
        return $this->middleware('auth:admin');        
    }

    public function planilha()
    {
        return new PlanilhaController;
    }

    public function aknaAPI()
    {
        return new AknaController;
    }

    public function index()
    {
        return view('admin.emkt.listas.index');
    }

    public function create()
    {
        return view('admin.emkt.listas.create')->with(['instituicoes' => $this->prefixo, 'tipos_de_acoes' => TipoDeAcao::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,csv,txt',
            'tipo_de_acao' => 'required|min:1|max:255|string',
            'date' => 'required|date'
        ]);

        if($request->hasFile('import_file'))
        {
            $extension = 'csv';
            $tipo_de_acao_id = $request->input('tipo_de_acao');
            $date = date('d-m-Y', strtotime($request->input('date')));
            $currentFile = $this->planilha()->load($request->file('import_file')->getRealPath());
            $hasAction = false;

            return $this->import($currentFile, $extension, $tipo_de_acao_id, $date, $hasAction);

        } else {
            return back()->with('danger', 'Você precisa importar um documento!');
        }
    }

    public function import($currentFile, $extension, $tipo_de_acao_id, $date, $hasAction)
    {
        $tipo_de_acao = TipoDeAcao::findOrFail($tipo_de_acao_id);
        $explode_date = explode('-', str_replace('/', '-', $date));

        $day = $explode_date[0];
        $month = $explode_date[1];
        $period = $explode_date[2];
        $period .= $month >=7 ? '-2' : '';

        if(isset($this->instituicoes))
        {

            $this->planilha()->filter($currentFile, $extension, str_replace('', '', str_replace(' ', '-', strtolower($tipo_de_acao->nome))), $day.'-'.$month.'-'.$period, 'akna_lists');

            $all_files = $this->planilha()->getFiles('akna_lists');

            $codigos_dos_processos = [];
            $nomes_das_listas = [];

            //dd($all_files);
        
            foreach($this->instituicoes as $instituicao)
            {
                $nome_do_arquivo = strtolower($this->prefixo[$instituicao->nome]).'-'.str_replace('-a-distancia', '', str_replace(' ', '-', strtolower($tipo_de_acao->nome))).'-'.$day.'-'.$month.'-'.$period.'.'.$extension;

                $nome_do_arquivo = str_replace(' ', '-', $nome_do_arquivo);

                if(in_array(public_path("akna_lists/$nome_do_arquivo"), $all_files))
                {
                    $nome_da_lista = 'teste-'.ucwords($this->prefixo[$instituicao->nome]).' - '.str_replace('-', ' ', $tipo_de_acao->nome).' - '.$day.'/'.$month.' - '.str_replace('-', '/',$period);
                    $status = $this->aknaAPI()->importarListaDeContatos($nome_da_lista, $nome_do_arquivo, $instituicao->nome, $instituicao->codigo_da_empresa);
                    Session::flash('message-'.$this->prefixo[$instituicao->nome], $status);
                    $nomes_das_listas[$this->prefixo[$instituicao->nome]] = $nome_da_lista;
                }
            }

            return $hasAction == true ? $nomes_das_listas : back();

        } else {

            return back()->with('warning', 'Não há instituições cadastradas para importar este arquivo!');
        }   
    }

    public function download($nome_da_lista, $extension)
    {
        $lista = Lista::where($nome_da_lista, '==', 'nome_da_lista')->first();
        return (new PlanilhaController)->download(eval($lista), $extension);
    }
}
