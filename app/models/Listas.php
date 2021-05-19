<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Listas{

    public function __construct()
    {}
    public function getAgentesActivos(Request $request, Response $response){

        global $pdo;

        try {
            $sector = 129;
            $sql="SELECT usu.id , upper(concat(empl.apellidos, ', ', empl.nombres)) nombre 
                    FROM cmx_usuarios usu
                    inner join rrhh_empleados empl on empl.id=usu.idempleado
                    WHERE ( usu.idsector IN (
                    select  id 
                    from    (select * from cmx_sectores
                            order by idsector, id) sectores_ord,
                            (select @pv := '%s') initialisation
                    where   find_in_set(idsector, @pv)
                    and     length(@pv := concat(@pv, ',', id))
                    )
                    OR usu.idsector=%s)
                    AND usu.activo=1
                    AND empl.activo=1
                    ORDER BY 2  ";
            $sql=sprintf($sql,$sector, $sector);
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