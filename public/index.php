<?php
// proyecto3/public/index.php
session_start();

/*
# Instalamos el framework
composer require slim/slim:3.*

# Instalamos el gestor de plantillas php/view
composer require slim/php-view

# Instalamos el módulo para mensajes Flash.
composer require slim/flash

// https://github.com/slimphp/Slim-Flash
*/


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\TokenAuthentication;

# Ajustar a la carpeta dónde tengamos vendor.
require __DIR__ . '/../vendor/autoload.php';

// Cargamos la clase basedatos.php
require __DIR__ . '/../app/db.php';

require_once __DIR__ . '/../app/models/Tickets.php';
require_once __DIR__ . '/../app/models/Kpi.php';
require_once __DIR__ . '/../app/models/Flow.php';
require_once __DIR__ . '/../app/models/Listas.php';
require __DIR__ . '/../app/Auth.php';
require __DIR__ . '/../app/UnauthorizedException.php';


// Nos conectamos a la base de datos
$pdo = Basedatos::getConexion();


$config = [
    'settings' => [
        'displayErrorDetails' => true
    ]
];

// Instanciamos la aplicación.
$app = new \Slim\App($config);

// Middleware de autenticación
$authenticator = function($request, TokenAuthentication $tokenAuth){

    /**
     * Try find authorization token via header, parameters, cookie or attribute
     * If token not found, return response with status 401 (unauthorized)
     */
    $token = $tokenAuth->findToken($request);


    /**
     * Call authentication logic class
     */
    $auth = new \app\Auth();


    /**
     * Verify if token is valid on database
     * If token isn't valid, must throw an UnauthorizedExceptionInterface
     */
    $auth->getUserByToken($token);

};

/**
 * Add token authentication middleware
 */
$app->add(new TokenAuthentication([
    'path' =>  ['/api/v1/flow', '/api/v1/tickets/'] ,
    'authenticator' => $authenticator
]));



// Get container
$container = $app->getContainer();

// Registramos las vistas
//$container['view'] = new \Slim\Views\PhpRenderer('../src/templates/');

// Registramos los mensajes flash
$container['flash'] = new \Slim\Flash\Messages();

// Definimos rutas de la aplicación
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Ejemplo de API REST con Slimframework.<br/><a href="https://manuais.iessanclemente.net/index.php/Slim_Framework_-_API_REST">API REST con SlimFramework</a>');
    return $response;
})->setName('root');

// Creación del grupo de rutas de la API.
$app->group('/api', function () use ($app) {
    // Versionado de la API
    $app->group('/v1', function () use ($app) {
        //tag de Ticket
        $app->group('/tickets', function () use ($app) {
            $tkt = new Tickets();
            $app->get('', function (Request $request, Response $response) use ($app, $tkt) {
                return $tkt->getTickets( $request, $response); 
            });
            $app->get('/', function (Request $request, Response $response) use ($app, $tkt) {
                return $tkt->getTickets( $request, $response); 
                });            
            $app->get('/{id}', function(Request $request, Response $response) use ($app, $tkt){
                return $tkt->getTicket( $request, $response); 
            });
        });
        //tag de Flow
        $app->group('/flow', function () use ($app) {
            $flow = new Flow();
            $app->get('/getEstados', function (Request $request, Response $response) use ($app, $flow) {
                return $flow->getEstadoFuturo( $request, $response); 
            });
            $app->put('/reasignar', function (Request $request, Response $response) use ($app, $flow) {
                return $flow->reAsignacion($request, $response);
            });
            $app->put('/suspender', function (Request $request, Response $response) use ($app, $flow) {
                return $flow->suspender($request, $response);
            });
            $app->put('/reactivar', function (Request $request, Response $response) use ($app, $flow) {
                return $flow->reactivar($request, $response);
            });
        });
        //tag de Listas
        $app->group('/listas', function () use ($app) {
            $tkt = new Listas();
            $app->get('/agentesActivos', function (Request $request, Response $response) use ($app, $tkt) {
                return $tkt->getAgentesActivos( $request, $response); 
            });
        });
        //tag KPI
        $app->group('/kpi', function () use ($app) {
            $kpi= new Kpi();
            $app->get('/kanban', function(Request $request, Response $response) use ($app, $kpi){
                return $tkt->getKanban( $request, $response); 
            });        
            $app->get('/kpi/{id}', function(Request $request, Response $response) use ($app, $kpi){
                return $kpi->getKpi( $request, $response); 
            });
        });
    });
});

/**
 * Agregando un leyer intermedio e autenticación para uno o todos los metodos, usar segun necesidad
 * Revisa si la consulta contiene un Header "Authorization" para validar
 */
function authenticate(Request $request, Response $response) {
    // Getting request headers
    $rs = array();
 
    // Verifying Authorization Header
    if ($request->getHeader('Authorization')) {
        //$db = new DbHandler(); //utilizar para manejar autenticacion contra base de datos
 
        // get the api key
        $token = $request->getHeader('Authorization');
        //var_dump($token);die();
        
        // validating api key
        if (!($token[0] == API_KEY)) { //API_KEY declarada en Config.php
            
            // api key is not present in users table
            $rs["error"] = true;
            $rs["message"] = "Acceso denegado. Token inválido";
            return $response->withJson($rs, 401);
            
            $app->stop(); //Detenemos la ejecución del programa al no validar
            
        } else {
            //procede utilizar el recurso o metodo del llamado
            return true;
        }
    } else {
        // api key is missing in header
        $rs["error"] = true;
        $rs["message"] = "Falta token de autorización";
        return $response->withJson($rs, 400);
        
        $app->stop();
    }
}


function crearUsuario(Request $request, Response $response)
{
    global $pdo;

    // Si necesitamos acceder a alguna variable global en el framework
    // Tenemos que pasarla con use($variable) en la cabecera de la función.
    // Va a devolver un objeto JSON con los datos de usuarios.

    $campos = $request->getParsedBody();

    try {
        // Preparamos la consulta a la tabla.
        $stmt = $pdo->prepare("insert into usuarios(nombre,apellidos,sueldo,edad) values(?,?,?,?)");
        $stmt->bindParam(1, $campos['nombre']);
        $stmt->bindParam(2, $campos['apellidos']);
        $stmt->bindParam(3, $campos['sueldo']);
        $stmt->bindParam(4, $campos['edad']);
        $stmt->execute();

        $datos = array('status' => 'ok', 'data' => 'Usuario dado de alta correctamente.');
        return $response->withJson($datos, 200);
    } catch (PDOException $e) {
        $datos = array('status' => 'error', 'data' => $e->getMessage());
        return $response->withJson($datos, 500);
    }
}

function actualizarUsuario(Request $request, Response $response)
{
    global $pdo;

    // Si necesitamos acceder a alguna variable global en el framework
    // Tenemos que pasarla con use($variable) en la cabecera de la función.
    // Va a devolver un objeto JSON con los datos de usuarios.

    $campos = $request->getParsedBody();

    try {
        // Preparamos la consulta a la tabla.
        $id = $request->getAttribute('id');

        $stmt = $pdo->prepare("select * from usuarios where id=?");
        $stmt->bindParam(1, $id);
        $stmt->execute();
        if ($stmt->rowCount() != 0) {

            $stmt = $pdo->prepare("update usuarios set nombre=?,apellidos=?,sueldo=?,edad=? where id=?");
            $stmt->bindParam(1, $campos['nombre']);
            $stmt->bindParam(2, $campos['apellidos']);
            $stmt->bindParam(3, $campos['sueldo']);
            $stmt->bindParam(4, $campos['edad']);
            $stmt->bindParam(5, $id);
            $stmt->execute();

            // Devolvemos ese array asociativo como un JSON con Status 200
            $datos = array('status' => 'ok', 'data' => 'Actualizado correctamente');
            return $response->withJson($datos, 200);
        } else {
            $datos = array('status' => 'error', 'data' => "No se ha encontrado el usuario con ID: $id.");
            return $response->withJson($datos, 404);
        }
    } catch (PDOException $e) {
        $datos = array('status' => 'error', 'data' => $e->getMessage());
        return $response->withJson($datos, 500);
    }
}

function eliminarUsuario(Request $request, Response $response)
{
    global $pdo;

    // Si necesitamos acceder a alguna variable global en el framework
    // Tenemos que pasarla con use($variable) en la cabecera de la función.
    // Va a devolver un objeto JSON con los datos de usuarios.

    try {
        // Preparamos la consulta a la tabla.
        $id = $request->getAttribute('id');

        $stmt = $pdo->prepare("select * from usuarios where id=?");
        $stmt->bindParam(1, $id);
        $stmt->execute();
        if ($stmt->rowCount() != 0) {

            $stmt = $pdo->prepare("delete from usuarios where id=?");
            $stmt->bindParam(1, $id);
            $stmt->execute();

            // Devolvemos ese array asociativo como un JSON con Status 204 No Content
            $datos = array('status' => 'ok', 'data' => 'Usuario borrado correctamente');
            return $response->withJson($datos, 204);
        } else {
            $datos = array('status' => 'error', 'data' => "No se ha encontrado el usuario con ID: $id.");
            return $response->withJson($datos, 404);
        }
    } catch (PDOException $e) {
        $datos = array('status' => 'error', 'data' => $e->getMessage());
        return $response->withJson($datos, 500);
    }
}



//////////////////////////////////////////////////////////////////////////////////////////////////
// A PARTIR DE AQUÍ ES UN EJEMPLO DE USO DE SLIM FRAMEWORK PARA HACER PARTES DE UNA APLICACIÓN.
//////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
//
// EJEMPLO DE USO DEL SLIM FRAMEWORK PARA GENERAR UNA APLICACIÓN.
// 
// 
//
// Ésto no formaría parte de la API REST. Ésto sería un ejemplo de aplicación
// que podemos generar con el framework Slim.
// Aquí se muestra un ejemplo de como se generaría una página utilizando vistas.
////////////////////////////////////////////////////////////////////////////

$app->get('/listadousuarios', function (Request $request, Response $response) {
    global $pdo;
    // Va a devolver un objeto JSON con los datos de usuarios.

    // Preparamos la consulta a la tabla.
    $stmt = $pdo->prepare("select * from usuarios");

    // Ejecutamos la stmt (si fuera necesario se le pasan parámetros).
    $stmt->execute();

    $response = $this->view->render($response, 'listadousuarios.php', ['resultados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    return $response;
});


// Cuando accedamos a /nuevousuario se mostrará un formulario de alta.
$app->get('/nuevousuario', function (Request $request, Response $response) {
    $response = $this->view->render($response, 'nuevousuario.php', ['mensajes' => $this->flash->getMessages()]);
    return $response;
})->setName('nuevousuario');


// Ruta que recibe los datos del formulario
$app->post('/nuevousuario', function (Request $request, Response $response) {
    global $pdo;

    // Si se reciben por GET $request->getQueryParams()
    // Si se reciben por POST $request->getParsedBody()
    $datosForm = $request->getParsedBody();
    // Preparamos la consulta de insert.
    $stmt = $pdo->prepare("insert into usuarios(nombre,apellidos,sueldo,edad)
                values (?,?,?,?)");
    $stmt->bindParam(1, $datosForm['nombre']);
    $stmt->bindParam(2, $datosForm['apellidos']);
    $stmt->bindParam(3, $datosForm['sueldo']);
    $stmt->bindParam(4, $datosForm['edad']);

    $estado = $stmt->execute();

    if ($estado)
        $this->flash->addMessage('mensaje', 'Usuario insertado correctamente.');
    else
        $this->flash->addMessage('error', 'Se ha producido un error al guardar datos.');

    // Redireccionamos al formulario original para mostrar 
    // los mensajes Flash.,
    return $response->withRedirect('nuevousuario');
});



// Ejecutamos la aplicación para que funcionen las rutas.
$app->run();