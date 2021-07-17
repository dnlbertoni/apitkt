<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Tickets{

    public function __construct()
    {}

    function getTickets(Request $request, Response $response){

        $payload  = json_decode($request->getBody(), true);
        $limit_ini=(isset($payload['pag_ini']))?$payload['pag_ini']:0;
        $limit_fin=(isset($payload['pag_fin']))?$payload['pag_fin']:4500;
        $fechoy = new DateTime();
        $fecdes=(isset($payload['fecdes']))?$payload['fecdes']:'2020-01-01';
        $fechas=(isset($payload['fechas']))?$payload['fechas']:$fechoy->format('Y-m-d');
        $tablero = ($request->getAttribute('tablero')!==null)?$request->getAttribute('tablero'):5;        
        global $pdo;

        try {
            $sql= "
            select 
                sk.nombre tablero,
                skt.nombre equipo,
                sks.nombre stage
                from sis_kanban sk  
                inner join sis_kanban_teams skt on sk.id = skt.idkanban 
                inner join sis_kanban_stages sks on sk.id=sks.idkanban 
                WHERE 1=1
                and sk.id=:tablero
                and skt.id=1
            ";
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindValue(':tablero', $tablero ,PDO::PARAM_INT );
            $stmt->execute();
            //var_dump($stmt);die();
            // Almacenamos los resultados en un array asociativo.
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql="
            SELECT 
                    ifnull(sk.id, 0) id_tablero, ifnull(sk.nombre, 'SinConfigurar') tablero, 
                    ifnull(skt.id,0) id_equipo,ifnull(skt.nombre,'SinConfigurar') equipo,
                    sks.id id_stage, sks.nombre stage, 
                    sp.id nro_pedido, 
                    sp.titulo, 
                    date_format(sp.fecalta,'%m/%d/%Y') fecha_ini, 
                    upper (concat(re.apellidos,', ',re.nombres))Agente,
                    concat('https://intranet.dilfer.com.ar/modulos/rrhh/',re.fotografia) Avatar,                    
                    sp.idestado idEstado,
                    est.valor Estado,
                    prio.valor prioridad,
                    cpj.valor Complejidad,
                    sp.horasestimadas Horasestimadas,
                    date_format(sp.fecfin,'%d/%m/%Y') fecha_fin 
            FROM sis_pedidos sp 
            inner join cmx_tipospedido ct on ct.id = sp.idtipopedido 
            inner join cmx_valores prio on sp.prioridad = prio.id 
            inner join cmx_valores est on est.id=sp.idestado 
            left join cmx_valores cpj on cpj.id=sp.complejidad 
            left join cmx_usuarios cu on cu.id = if(sp.idusuario=0,sp.idusuario_potencial,sp.idusuario)
            left join rrhh_empleados re on re.id=cu.idempleado 
            LEFT JOIN sis_kanban_team_tipopedidos sktt on sktt.idtipopedido = sp.idtipopedido
            left join sis_kanban_teams skt on skt.id = sktt.idkanban_team 
            left join sis_kanban_stage_estados skse on skse.idestado = sp.idestado 
            left join sis_kanban_stages sks on sks.id=skse.idkanban_stage 
            left join sis_kanban sk on sk.id=skt.idkanban and sk.id=sks.idkanban 
            where 1=1
                AND ct.activo = 1 
                and fecalta BETWEEN :fecdes AND :fechas 
                and (sp.idestado not in (20,25,312) OR CONCAT(YEAR (sp.fecfin),week(sp.fecfin))=(CONCAT(YEAR (:fechas),WEEK(:fechas)))) 
                and sk.id = :tablero
            order by sk.nombre , skt.nombre desc ,sks.orden, sp.prioridad desc ,sp.fecalta desc
            limit :pag_ini,:pag_fin 
            ";
            //$data = array( , $fechas,$estados, $limit_ini, $limit_fin );
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindValue(':fecdes', $fecdes);
            $stmt->bindValue(':fechas', $fechas);
            $stmt->bindValue(':pag_ini', $limit_ini ,PDO::PARAM_INT);
            $stmt->bindValue(':pag_fin', $limit_fin ,PDO::PARAM_INT );
            $stmt->bindValue(':tablero', $tablero ,PDO::PARAM_INT );
            $stmt->execute();
            //var_dump($stmt);die();
            // Almacenamos los resultados en un array asociativo.
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = array( 'list'=>array());
            $z_aux = false;
            $d = array();
            foreach ($resultados as $r){
                $x=$r['id_tablero'];
                $w=$r['id_equipo'];
                $z=$r['id_stage'];
                $y=$r['nro_pedido'];
                if($z!=$z_aux){
                    if(!empty($d)){
                        $d['tasks']    = $tasks; 
                        $d['cantidad'] = count($tasks);
                        array_push($data['list'],$d);
                    }
                    $d = array(
                        'id'    => $r['id_stage'], 
                        'name'  => $r['stage'],
                        'tasks'  => array(),
                        'cantidad' => 0
                    );
                    $tasks = array();
                    $z_aux=$r['id_stage'];
                };
                $task = array(  'id' => $r['nro_pedido'],
                                'description' => $r['titulo'],
                                'date'        => $r['fecha_ini'],
                                'priority'    => $r['prioridad'],
                                'avatar'      => $r['Avatar']);
                array_push($tasks, $task);
            }
            if(!empty($d)){
                $d['tasks']=$tasks; 
                $d['cantidad'] = count($tasks);
                array_push($data['list'],$d);
            };
            // Devolvemos ese array asociativo como un string JSON.
            return $response->withJson($data,200)
                            ->withHeader('Access-Control-Allow-Origin', '*')
                            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (PDOException $e) {
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

    function addTicket(Request $request, Response $response){
        global $pdo;
        $payload  = json_decode($request->getBody(), true);
        $protecto = ($payload['idtipopedido']==137)?1:0;

        $stmt=$pdo->beginTransaction();
        try {
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare("INSERT INTO sis_pedidos 
                                            ( titulo, fecalta, idcliente, idestado, idsector, idtipopedido, proyecto )
                                    values  ( ?,      NOW(),   ?,         18,       ?,        ?,            ?);
                        ");
            $stmt->bindParam(1, $payload['titulo']);
            $stmt->bindParam(2, $payload['idcliente']);
            $stmt->bindParam(3, $payload['idsector']);
            $stmt->bindParam(4, $payload['idtipopedido']);
            $stmt->bindParam(5, $proyecto);
            $stmt->execute();
            $newID=$stmt->lastInsertId();
            if ($newID>0) {
                // Creamos el Log.
                try {            
                    $sql="INSERT INTO sis_pedidos_detalles
                                    ( idpedido, fecha, detalle, idestado )
                            values  ( ?,        NOW(), ?,       ? );";
                    $data = array($newID,$payload['detalle'], 18);
                    // Preparamos la consulta a la tabla.
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                } catch (PDOException $e) {
                    $stmt=$pdo->rollBack();                
                    $datos = array('status' => 'error1', 'data' => 'No se ha podido crear el detalle');
                    return $response->withJson($datos, 405);
                }                 
                $stmt=$pdo->commit();                
                // Devolvemos ese array asociativo como un JSON con Status 200
                $datos = array('status' => 'Ok', 'data' => "Se creo el ticket");    
                return $response->withJson($resultados, 201);
            } else {
                $stmt=$pdo->rollBack();                
                $datos = array('status' => 'error', 'data' => "No se ha podido crear el ticket");
                return $response->withJson($datos, 405);
            }
        } catch (PDOException $e) {
            $stmt=$pdo->rollBack();                
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 405);
        }
    }

    function getTicket(Request $request, Response $response){
        global $pdo;
    
        try {
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare("select * from sis_pedidos where id=?");
            $id = $request->getAttribute('id');
            $stmt->bindParam(1, $id);
            $stmt->execute();
    
            if ($stmt->rowCount() != 0) {
                // Almacenamos los resultados en un array asociativo.
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Devolvemos ese array asociativo como un JSON con Status 200
    
                return $response->withJson($resultados, 200);
            } else {
                $datos = array('status' => 'error', 'data' => "No se ha encontrado el usuario con ID: $id.");
                return $response->withJson($datos, 404);
            }
        } catch (PDOException $e) {
            $datos = array('status' => 'error', 'data' => $e->getMessage());
            return $response->withJson($datos, 500);
        }
    }

}
