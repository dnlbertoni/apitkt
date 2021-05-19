<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Tickets{

    public function __construct()
    {}

    function getTickets(Request $request, Response $response){

        $payload  = json_decode($request->getBody(), true);
        $protecto = ($payload['idtipopedido']==137)?1:0;
        $limit_ini=(isset($payload['pag_ini']))?$payload['pag_ini']:0;
        $limit_fin=(isset($payload['pag_fin']))?$payload['pag_fin']:25;

        $fechoy = new DateTime();
        $fecdes=(isset($_GET['fecdes']))?$_GET['fecdes']:'2020-02-19';
        $fechas=(isset($_GET['fechas']))?$_GET['fechas']:$fechoy->format('Y-m-d');
        $sector=(isset($_GET['sector']))?$_GET['sector']:false;
        $agente=(isset($_GET['agente'])&& trim($_GET['agente']) != '' )?' and ( s.idusuario = '. $_GET['agente']. ' or s.idusuario_potencial = '. $_GET['agente'].' ) ':' ';
        $estados=(isset($_GET['estados']) && trim($_GET['estados']) != '')?' and s.idestado in (' .$_GET['estados'] .') ':'';
        $est_default = "
        AND (
            s.idestado not IN(25,20)
            or (
                    s.idestado in (25,20) AND (
                        fecalta BETWEEN '%s' AND NOW() OR fecfin BETWEEN '%s' AND NOW()
                    )
                ) 
            )";
        $est_default = sprintf($est_default,$fecdes,$fechas);
        $estados=($estados=='')?$est_default:$estados;
        
        if(!$sector){
            $whereSector=' is null';
        }else{
            $whereSector = '='.$sector;
        }
        global $pdo;

        try {
            $sql="SELECT 
            date_format(s.fecalta,'%s') Fecha, s.id NroPedido,
            if (s.proyecto = 0 ,p.tipopedido, 'PROYECTO') Tipopedido,
            s.titulo Titulo,
            ifnull(upper (concat(usuempl.apellidos,', ',usuempl.nombres)),'') UsuarioAsignado,
            v.valor Estado,
            CONCAT('https://intranet.dilfer.com.ar/modulos/sistemas/index.php?modulo=pedido&comando=mostrar&id=',s.id) Link,
            ifnull(upper (concat(usuempl.apellidos,', ',usuempl.nombres)), upper (concat(usupot.apellidos,', ',usupot.nombres)) ) Usuariopotencial,
            vc.valor Complejidad,
            s.horasestimadas Horasestimadas,
            s.idproveedor,
            s.nro_tkt_externo
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
            AND p.idsector %s 
            AND p.activo = 1
            %s
            order by s.fecalta desc
            limit %d, %d
            ";
            $sql= sprintf($sql,'%d/%m/%Y', $whereSector,$agente . $estados ,$limit_ini, $limit_fin);
            // Preparamos la consulta a la tabla.
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
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