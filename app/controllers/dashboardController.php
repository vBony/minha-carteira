<?php
class dashboardController extends controllerHelper{
    public function index(){
        $data = array();
        $request = $_POST;

        $Sessao = new Sessao();
        $Usuario = new Usuario();

        if(!empty($request['access_token']) && $Sessao->validarToken($request['access_token'])){
            $sessao = $Sessao->buscarValidoPorToken($request['access_token']);

            $Transacoes = new Transacoes();

            $mesano = date('m-Y');

            $data['user'] = $Usuario->safeData($Usuario->buscar($sessao['ss_usu_id']));
            $data['access_token'] = $sessao['ss_token'];

            $data['mesanos'] = [
                'mes_ano' => $mesano,
                'prox_mesano' => $this->getMesAno($mesano, 'after'),
                'ant_mesano' =>  $this->getMesAno($mesano, 'before')
            ];

            $data['resumo'] = $Transacoes->calcularResumosMes($sessao['ss_usu_id'], $mesano);

            $data['transacoes'] = $Transacoes->buscar($sessao['ss_usu_id'], $mesano);

            $this->sendJson(['data' => $data]);
        }else{
            return http_response_code(401);
        }
    }

    public function categorias(){
        $Categorias = new Categorias();
        if(isset($_POST['tipo']) && !empty($_POST['tipo'])){
            $tipo = $_POST['tipo'];
            $data['categorias'] = $Categorias->buscar($tipo);

            $this->sendJson($data);
        }
    }

    public function inserirTransacao(){
        $Transacoes = new Transacoes();
        $Sessao = new Sessao();
        $Usuario = new Usuario();

        $data = $_POST['data'];
        $data['tra_situacao'] = $data['tra_situacao'] == 'true' ? 1 : 0;

        $mesano = $_POST['mesano'];
        $access_token = isset($_POST['access_token']) && !empty($_POST['access_token']) ? $_POST['access_token'] : null;

        if(empty($access_token) && !$Sessao->validarToken($access_token)){
            return http_response_code(401);
        }

        $sessao = $Sessao->buscarValidoPorToken($access_token);
        $usuario = $Usuario->buscar($sessao['ss_usu_id']);

        $data['tra_valor'] = isset($data['tra_valor']) && !empty($data['tra_valor']) ? $this->changeToFloat($data['tra_valor']) : null;

        if(!$Transacoes->validate($data)){
            $this->sendJson(array("errors" => $Transacoes->errors));
        }else{
            if(isset($data['tra_id']) && !empty($data['tra_id'])){
                $Transacoes->alterar($data, $usuario['usu_id']);
            }else{
                $Transacoes->inserir($data, $usuario['usu_id']);
            }

            $transacoes = $Transacoes->buscar($usuario['usu_id'], $mesano);

            $this->sendJson([
                'access_token' => $sessao['ss_token'],
                'transacoes' => $transacoes,
                'resumo' => $Transacoes->calcularResumosMes($usuario['usu_id'], $mesano)
            ]);
        }
    }

    public function buscarTransacao(){
        $Sessao = new Sessao();
        $Usuario = new Usuario();
        $Transacoes = new Transacoes();

        $id = $_POST['id'];
        $access_token = $_POST['access_token'];
        $mesano = $_POST['mesano'];

        if(empty($access_token) && !$Sessao->validarToken($access_token)){
            return http_response_code(401);
        }

        $sessao = $Sessao->buscarValidoPorToken($access_token);
        $usuario = $Usuario->buscar($sessao['ss_usu_id']);

        $transacao = $Transacoes->buscarPorId($id, $usuario['usu_id']);

        if(!empty($transacao)){
            $this->sendJson([
                'transacao' => $transacao
            ]);
        }else{
            return http_response_code(401);
        }
    }

    public function efetivarTransacao(){
        $Sessao = new Sessao();
        $Usuario = new Usuario();
        $Transacao = new Transacoes();

        $idTransacao = $_POST['id'];
        $access_token = $_POST['access_token'];
        $mesano = $_POST['mesano'];

        if(empty($access_token) && !$Sessao->validarToken($access_token)){
            return http_response_code(401);
        }

        $sessao = $Sessao->buscarValidoPorToken($access_token);
        $usuario = $Usuario->buscar($sessao['ss_usu_id']);

        if(!$Transacao->efetivar($idTransacao, $usuario['usu_id'])){
            return http_response_code(401);
        }else{
            $this->sendJson([
                'access_token' => $sessao['ss_token'],
                'transacoes' => $Transacao->buscar($usuario['usu_id'], $mesano),
                'resumo' => $Transacao->calcularResumosMes($usuario['usu_id'], $mesano),
            ]);
        }
    }

    public function deletarTransacao(){
        $Sessao = new Sessao();
        $Usuario = new Usuario();
        $Transacao = new Transacoes();

        $idTransacao = $_POST['id'];
        $access_token = $_POST['access_token'];
        $mesano = $_POST['mesano'];

        if(empty($access_token) && !$Sessao->validarToken($access_token)){
            return http_response_code(401);
        }

        $sessao = $Sessao->buscarValidoPorToken($access_token);
        $usuario = $Usuario->buscar($sessao['ss_usu_id']);

        if(!$Transacao->deletar($idTransacao, $usuario['usu_id'])){
            return http_response_code(401);
        }else{
            $this->sendJson([
                'access_token' => $sessao['ss_token'],
                'transacoes' => $Transacao->buscar($usuario['usu_id'], $mesano),
                'resumo' => $Transacao->calcularResumosMes($usuario['usu_id'], $mesano),
            ]);
        }
    }

    public function buscarPorMesAno(){
        $Sessao = new Sessao();
        $Usuario = new Usuario();
        $Transacoes = new Transacoes();

        $access_token = $_POST['access_token'];

        if(empty($access_token) && !$Sessao->validarToken($access_token)){
            return http_response_code(401);
        }

        $mesano = $this->validarMesAno($_POST['mesano']) == false ? date('m-Y') : $_POST['mesano'];

        $sessao = $Sessao->buscarValidoPorToken($access_token);
        $usuario = $Usuario->buscar($sessao['ss_usu_id']);

        $mesanos = [
            'mes_ano' => $mesano,
            'prox_mesano' => $this->getMesAno($mesano, 'after'),
            'ant_mesano' =>  $this->getMesAno($mesano, 'before')
        ];

        return $this->sendJson([
            'access_token' => $sessao['ss_token'],
            'mesanos' => $mesanos,
            'resumo' => $Transacoes->calcularResumosMes($sessao['ss_usu_id'], $mesano),
            'transacoes' => $Transacoes->buscar($sessao['ss_usu_id'], $mesano)
        ]);
    }

    private function changeToFloat($value){
        return (float) number_format(str_replace(",",".",str_replace(".","",$value)), 2, '.', '');
    }

    private function validarMesAno($ma){
        if(empty($ma)){
            return false;
        }else{
            $mesAno = $ma;
            $mesAnoArr = explode("-", $mesAno);

            if(count($mesAnoArr) == 2){
                $mes = $mesAnoArr[0];
                $ano = $mesAnoArr[1];

                if(!checkdate($mes, 01, $ano)){
                    return false;
                }else{
                    return $ma;
                }
            }
        }
    }

    private function getMesAno($mesano, $action){
        $mesanoArr = explode('-', $mesano);
        $mes = (int) $mesanoArr[0];
        $ano = (int) $mesanoArr[1];

        if($action == 'before'){
            if($mes == 1){
                $ano = $ano - 1;
                $mes = 12;

            }else{
                $mes = $mes - 1;
                if($mes < 10){
                    $mes = '0'.$mes;
                }
            }

            return $mes . '-' . $ano;
        }

        if($action == 'after'){
            if($mes == 12){
                $mes = '01';
                $ano = $ano + 1;
            }else{
                $mes = $mes + 1;
                if($mes < 10){
                    $mes = '0'.$mes;
                }
            }
            return $mes . '-' . $ano;
        }

    }
}

?>