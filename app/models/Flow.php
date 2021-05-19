<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Flow{

    private $ErrorCode = null;

    private $idusuario_valido=false;
    private $idestado_valido=false;

    public function __construct(){

    }

    public function getEstadoFuturo(Request $request, Response $response){

        global $pdo;
        $payload = json_decode($request->getBody(), true);
        $id = (isset($payload['id']))?$payload['id']:null;
        $idusuario = (isset($payload['idusuario']))?$payload['idusuario']:null;

        try {
            $sql="SELECT val.id , val.valor nombre 
            FROM sis_estados est_next 
            inner join sis_pedidos sp on sp.idestado = est_next.idestado 
            inner join cmx_valores val on val.id=est_next.idestadosig
            where sp.id=?;";
            //$data = array($id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($id);
            // Almacenamos los resultados en un array asociativo.
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Devolvemos ese array asociativo como un string JSON.
            return $response->withJson($resultados, 200);
        } catch (PDOException $e) {
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

    public function reAsignacion(Request $request, Response $response){

        global $pdo;
        $json = $request->getBody();
        $data = json_decode($json, true);
        $id = (isset($data['id']))?$data['id']:null;
        $idusuario = (isset($data['idusuario']))?$data['idusuario']:null;
        $reasignado = (isset($data['reasignado']))?$data['reasignado']:null;

        $valores['idusuario'] = $idusuario;
        $valores['reasignado'] = $reasignado;
        $errorValidacion = $this->flowControlError('reasignacion', $id, $valores);
        //var_dump($errorValidacion); die();

        if($errorValidacion){
            $datos = array('status' => $errorValidacion , 'data' => $this->getErrorMessage($errorValidacion));
            return $response->withJson($datos, 200);
        }
        $pdo->beginTransaction();
        //cambia el usuario 
        try {            
            $sql="UPDATE sis_pedidos sp  set sp.idusuario=? 
                  where sp.id=?";
            $data = array($reasignado,$id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            // Almacenamos los resultados en un array asociativo.
            //$resultados = $stmt->fetch();
        } catch (PDOException $e) {
            $pdo->rollback();
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
        //inserta nota del cambio
        try {            
            $sql="INSERT INTO sis_pedidos_detalles
                          ( idpedido, fecha, detalle,                   idcontesta, idestado )
                    SELECT  id,       NOW(), 'Reasignacion de Usuario', ? , idestado 
                    FROM sis_pedidos 
            WHERE id=?;";
            $data=array($idusuario,$id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            // Devolvemos ese array asociativo como un string JSON.
            $datos = array('status' => 'ok', 'data' => '');
            $pdo->commit();
            return $response->withJson($datos, 200);
        } catch (PDOException $e) {
            $pdo->rollback();
            $datos = array('status' => 'error1', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

    public function suspender(Request $request, Response $response){

        global $pdo;
        $json = $request->getBody();
        $data = json_decode($json, true);
        $id = (isset($data['id']))?$data['id']:null;
        $idusuario = (isset($data['idusuario']))?$data['idusuario']:null;
        $idestado=316; //suspender

        $valores['idusuario'] = $idusuario;
        $valores['idestadosig'] = $idestado; 
        $errorValidacion = $this->flowControlError('suspender', $id, $valores);
        //var_dump($errorValidacion); die();

        if($errorValidacion){
            $datos = array('status' => $errorValidacion , 'data' => $this->getErrorMessage($errorValidacion));
            return $response->withJson($datos, 200);
        }
        //actualizo estado
        try {            
            $sql="UPDATE sis_pedidos sp  set sp.idestado=? 
                  where sp.id=?";
            $data = array($idestado,$id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            // Almacenamos los resultados en un array asociativo.
            //$resultados = $stmt->fetch();
            // Devolvemos ese array asociativo como un string JSON.
            $this->logueo('Suspencion del Pedido', $idusuario, $id);
            $datos = array('status' => 'ok', 'data' => '');
            return $response->withJson($datos, 200);
        } catch (PDOException $e) {
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

    public function reactivar(Request $request, Response $response){

        global $pdo;
        $json = $request->getBody();
        $data = json_decode($json, true);
        $id = (isset($data['id']))?$data['id']:null;
        $idusuario = (isset($data['idusuario']))?$data['idusuario']:null;

        $valores['idusuario'] = $idusuario;
        $errorValidacion = $this->flowControlError('reactivar', $id, $valores);
        //var_dump($errorValidacion); die();

        if($errorValidacion){
            $datos = array('status' => $errorValidacion , 'data' => $this->getErrorMessage($errorValidacion));
            return $response->withJson($datos, 200);
        }
        //actualizo estado
        try {            
            $sql="UPDATE sis_pedidos set idestado=(
                                                    SELECT idestado 
                                                        from sis_pedidos_detalles spd  
                                                        where idpedido = ? 
                                                        and idestado <> 316 order by id desc limit 0,1 )
                where id=?";
            $data = array($id,$id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            // Almacenamos los resultados en un array asociativo.
            //$resultados = $stmt->fetch();
            // Devolvemos ese array asociativo como un string JSON.
            $this->logueo('Reactivacion del Pedido', $idusuario, $id);
            $datos = array('status' => 'ok', 'data' => '');
            return $response->withJson($datos, 200);
        } catch (PDOException $e) {
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

    private function logueo($mensaje, $idusuario, $id){
        global $pdo;
        //inserta nota del cambio
        try {            
            $sql="INSERT INTO sis_pedidos_detalles
                            ( idpedido, fecha, detalle,                   idcontesta, idestado )
                    SELECT  id,       NOW(), ? , ? , idestado 
                    FROM sis_pedidos 
            WHERE id=?;";
            $data = array($mensaje,$idusuario, $id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            return true;
        } catch (PDOException $e) {
            $datos = array('status' => 'error1', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }        
    }

    private function flowControlError($accion, $tkt, $valores=array()){
        if($tkt===null){
            return 'F000';
        };
        if(!$this->_valEstadoTkt($tkt)){
            return 'F001';
        };
        switch ($accion){
            case 'reasignacion':
                if(isset($valores['idusuario'])){
                    if(!$this->_valIdusuario($valores['idusuario'])){
                        return 'F002';
                    }else{
                        if(!$this->_valIdusuario($valores['reasignado'])){                        
                            return 'F002';
                        }else{
                            return false;
                        }
                    }
                }else{
                    return 'F002';
                }
                break;
            case 'suspender':
                if(isset($valores['idusuario'])){
                    if(!$this->_valIdusuario($valores['idusuario'])){
                        return 'F002';
                    }else{
                        if(!$this->_valFlujoIdestado($tkt,$valores['idestado'])){
                            return 'F003';
                        }else{
                            return false;
                        }
                    }
                }else{
                    return 'F002';
                }
                break;  
            case 'reactivar':
                if(isset($valores['idusuario'])){
                    if(!$this->_valIdusuario($valores['idusuario'])){
                        return 'F002';
                    }else{
                        return false;
                    }
                }else{
                    return 'F002';
                }
                break;                      
        }
    }

    public function getErrorMessage(string $errorCode = null){
        switch ($errorCode){
            case 'F000':
                $rta = 'No es un Ticket Valido';
            break;            
            case 'F001':
                $rta = 'El Ticket esta en un estado que no puede ser modificado';
            break;
            case 'F002':
                $rta = 'El agente no es valido';
            break;
            case 'F003':
                $rta = 'El Estado actual no permite el nuevo Estado';
            break;
            default: 
                $rta = 'Error Inesperado';
            break;
        }
        return $rta;
    }

    private function _valIdusuario($idusuario){
        global $pdo;
        try {            
            $sql="SELECT usu.id 
                    FROM cmx_usuarios usu
                    inner join rrhh_empleados empl on empl.id=usu.idempleado
                    where usu.id=%s
                    and usu.activo=1
                    and empl.activo=1
                    ";
            $sql = sprintf($sql,$idusuario);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
            if(!$row){
                return false;
            }else{
                return true;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    private function _valFlujoIdestado($tkt, $idestadosig){
        global $pdo;
        try {            
            if($idestado != $idestadosig ){
                $sql="SELECT * 
                        FROM sis_estados est 
                        inner join sis_pedidos sp on sp.idestado =est.idestado 
                        where est.idestadosig =?
                        and sp.id=?
                ";
                $data = array($idestadosig, $tkt);
                // Preparamos la consulta a la tabla.
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                $row = $stmt->fetch();
                if(!$row){
                    return false;
                }else{
                    return true;
                }
            }else{
                return true;
            }    
        } catch (PDOException $e) {
            return false;
        }
    }

    private function _valEstadoTkt($id){ //valido la no modificacion del TKT
        global $pdo;
        try {            
            $sql="SELECT * FROM sis_pedidos
                    where id=%s
                    and idestado not in (20,25,312)
            ";
            $sql = sprintf($sql,$id);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
            if(!$row){
                return false;
            }else{
                return true;
            }
        } catch (PDOException $e) {
            return false;
        }
    }


}