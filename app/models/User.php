<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class User{

    public function __construct()
    {}
    public function login(Request $request, Response $response){

        global $pdo;
        $payload  = json_decode($request->getBody(), true);
        $username = (isset($payload['usuario']))?$payload['usuario']:false;
        $password = (isset($payload['password']))?$payload['password']:false;

        if($username && $password){
            try {
                $sql="SELECT usu.id , upper(concat(empl.apellidos, ', ', empl.nombres)) nombre 
                        FROM cmx_usuarios usu
                        inner join rrhh_empleados empl on empl.id=usu.idempleado
                        WHERE 1=1
                        AND usu.activo=1
                        AND empl.activo=1
                        and usu.usuario=?
                        ORDER BY 2  ";
                $data=array($username);
                // Preparamos la consulta a la tabla.
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                // Almacenamos los resultados en un array asociativo.
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Devolvemos ese array asociativo como un string JSON.
                return $response->withJson($resultados, 200);
            } catch (PDOException $e) {
                $datos = array('status' => 'error', 'data' => $e->getMessage());
                return $response->withJson($datos, 500);
            }    
        }else{
            $datos = array('status' => 'error', 'data' => 'No se enviaron datos validos');
            return $response->withJson($datos, 404);
        }
    }

    public function logout(Request $request, Response $response){
        global $pdo;
        $payload  = json_decode($request->getBody(), true);
        $username = (isset($payload['usuario']))?$payload['usuario']:false;

        if($username){
            try {
                $sql="SELECT usu.id 
                        FROM cmx_usuarios usu
                        WHERE 1=1
                        and usu.usuario=?;";
                $data=array($username);
                // Preparamos la consulta a la tabla.
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                // Almacenamos los resultados en un array asociativo.
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Devolvemos ese array asociativo como un string JSON.
                return $response->withJson($resultados, 200);
            } catch (PDOException $e) {
                $datos = array('status' => 'error', 'data' => $e->getMessage());
                return $response->withJson($datos, 500);
            }    
        }else{
            $datos = array('status' => 'error', 'data' => 'No se enviaron datos validos');
            return $response->withJson($datos, 404);
        }
    }
} 