<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Tickets{

    public function __construct()
    {}

    function getTickets(Request $request, Response $response){

        $payload  = json_decode($request->getBody(), true);
        $limit_ini=(isset($payload['pag_ini']))?$payload['pag_ini']:0;
        $limit_fin=(isset($payload['pag_fin']))?$payload['pag_fin']:25;
        $fechoy = new DateTime();
        $fecdes=(isset($payload['fecdes']))?$payload['fecdes']:'2020-01-01';
        $fechas=(isset($payload['fechas']))?$payload['fechas']:$fechoy->format('Y-m-d');
    
        global $pdo;

        try {
            $sql="SELECT 
            s.id NroPedido,
            date_format(s.fecalta,'%d/%m/%Y') fecha_ini, 
            date_format(s.fecfin,'%d/%m/%Y') fecha_fin, 
            if (s.proyecto = 0 ,p.tipopedido, 'PROYECTO') Tipopedido,
            s.titulo Titulo,
            ifnull(upper (concat(usuempl.apellidos,', ',usuempl.nombres)),'') UsuarioAsignado,
            s.idestado idEstado,
            v.valor Estado,
            CONCAT('http://localhost:8080/api/v1/tickets/',s.id) Link,
            ifnull(upper (concat(usuempl.apellidos,', ',usuempl.nombres)), upper (concat(usupot.apellidos,', ',usupot.nombres)) ) Usuariopotencial,
            vc.valor Complejidad,
            s.horasestimadas Horasestimadas
            FROM sis_pedidos s
            inner join cmx_sectores sect on sect.id=s.idsector
            left JOIN sis_pedidos_datos sd ON sd.idpedido=s.id
            INNER JOIN cmx_valores v ON v.id=s.idestado
            INNER JOIN cmx_tipospedido p ON p.id=s.idtipopedido
            INNER JOIN cmx_usuarios cli ON cli.id=s.idcliente
            INNER JOIN rrhh_empleados cliempl ON cliempl.id=cli.idempleado
            left JOIN cmx_usuarios usu ON usu.id=s.idusuario
            left JOIN rrhh_empleados usuempl ON usuempl.id=usu.idempleado
            left JOIN cmx_usuarios upot ON upot.id=s.idusuario_potencial
            left JOIN rrhh_empleados usupot ON usupot.id=upot.idempleado
            left JOIN cmx_valores vc ON vc.id=s.complejidad
            WHERE 1=1
            AND p.idsector is null
            AND p.activo = 1 
            and (fecalta BETWEEN :fecdes AND :fechas OR fecfin BETWEEN :fecdes AND :fechas ) 
            order by s.fecalta desc
            limit :pag_ini,:pag_fin 
            ";
            //$data = array( , $fechas,$estados, $limit_ini, $limit_fin );
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindValue(':fecdes', $fecdes);
            $stmt->bindValue(':fechas', $fechas);
            $stmt->bindValue(':pag_ini', $limit_ini ,PDO::PARAM_INT);
            $stmt->bindValue(':pag_fin', $limit_fin ,PDO::PARAM_INT );
            $stmt->execute();
            //var_dump($stmt);die();
            // Almacenamos los resultados en un array asociativo.
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Devolvemos ese array asociativo como un string JSON.
            return $response->withJson($resultados, 200);
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