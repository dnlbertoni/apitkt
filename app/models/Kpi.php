<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Kpi{

    public function __construct()
    {}

    public function getKpi(Request $request, Response $response){
        $id = $request->getAttribute('id');
        switch ($id){
            case 1: 
                return $this->getKpiPendiente($request, $response);
                break;
            case 2: 
                return $this->getKpiBasadosProyectos($request, $response);
                break;    
            case 3: 
                return $this->getKpiProyectos($request, $response);
                break;    
            case 4: 
                return $this->getKpiBacklog($request, $response);
                break;    
            default:
            $datos = array('status' => 'error', 'data' => 'No es un KPI definido.');
            return $response->withJson($datos, 422);
        }
    }

    private function getKpiPendiente(Request $request, Response $response){

        global $pdo;

        try {
            $sql="SELECT 'Pendiente' kpi,format(count(1),0) q, 10 max, format(if(count(1)/10*100>99,99,count(1)/10*100),2) avance FROM sis_pedidos
            where idestado=18";
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
    private function getKpiBasadosProyectos(Request $request, Response $response){

        global $pdo;

        try {
            $sql="SELECT 'BasadosProyectos' kpi,format(count(1),0) q, 12 max, format(if(count(1)/12*100>99,99,count(1)/12*100),2) avance FROM sis_pedidos
            where idtipopedido=137 and idestado not in (25,20,312)";
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

    private function getKpiProyectos(Request $request, Response $response){

        global $pdo;

        try {
            $sql="SELECT 'Proyectos' kpi,format(count(1),0) q, 3 max, format(if(count(1)/12*100>99,99,count(1)/12*100),2) avance FROM sis_pedidos
            where idtipopedido=138 and idestado not in (25,20,312)";
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
    private function getKpiBacklog(Request $request, Response $response){

        global $pdo;

        try {
            $sql="SELECT 'Backlog' kpi, format(sum(case when idestado in (25,20,312) then 1 else 0 end) /count(1),2) backlog, 1 max,
            format(if(((sum(case when idestado in (25,20,312) then 1 else 0 end) /count(1)))*100>99,99,((sum(case when idestado in (25,20,312) then 1 else 0 end) /count(1)))*100),2) avance
            from sis_pedidos s 
            INNER JOIN cmx_tipospedido p ON p.id=s.idtipopedido
            WHERE 1=1
            AND p.idsector is null 
            AND p.activo = 1
            and fecalta > '2020-01-01'";
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
    function getKanban(Request $request, Response $response){
        global $pdo;
        try {
            $sql="
            SELECT 
            sp.idusuario,  
            cu.usuario Agente ,  
            sum(case when sp.idestado in (315,316,321) then 1 else 0 end) backlog,
            sum(case when sp.idestado in (19,21,274,280) then 1 else 0 end) todo,
            sum(case when sp.idestado in (313) then 1 else 0 end) delivered,
            ifnull(fin.q,0)  fin
            FROM ( select 
                    case when idusuario > 0 and idusuario is not null 
                            then idusuario 
                            else idusuario_potencial end idusuario,
                        idtipopedido, 
                        idestado, 
                        fecalta, 
                        fecfin, 
                        proyecto, 
                        idusuario_potencial 
                        from sis_pedidos ) sp
            inner join cmx_tipospedido ct on ct.id = sp.idtipopedido 
            left join (
            select u.id id,upper(CONCAT(re.apellidos, ', ', re.nombres)) usuario, u.activo activo from cmx_usuarios u
            inner join rrhh_empleados re on re.id=u.idempleado
            ) cu on cu.id = sp.idusuario 
            left join (  
            select spx.idusuario usu, count(1) q from sis_pedidos spx 
            inner join cmx_tipospedido ctx on ctx.id = spx.idtipopedido 
            where 1=1
            and ctx.idsector is null
            and ctx.activo = 1
            and spx.proyecto =0 
            and CONCAT(YEAR(spx.fecfin), WEEK(spx.fecfin,4))=CONCAT(YEAR (NOW()),WEEK(NOW(),4) ) 
            group by spx.idusuario
            ) fin on fin.usu=sp.idusuario 
            where 1=1
            and ct.idsector is null
            and ct.activo = 1
            and cu.activo = 1
            and sp.fecfin ='0000-00-00'
            and fecalta > '2020-01-01'
            and sp.idestado not in (20)
            and sp.proyecto =0   
            group by sp.idusuario
            order by 2
            ";
//            $sql= sprintf($sql,'%d/%m/%Y', $whereSector,$agente . $estados ,$limit_ini, $limit_fin);
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
}