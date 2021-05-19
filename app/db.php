<?php
require_once __DIR__ . '/config.php';

class Basedatos
{

    // Propiedad estática dónde almacenaremos la referencia de la conexión
    // a la base de datos mariadb.
    private static $conexion = false;

    private function __construct()
    {
        try {
            $cadenaConexion = "mysql:host=" . DB_SERVIDOR . ";port=" . DB_PUERTO . ";dbname=" . DB_BASEDATOS . ";charset=utf8";
            self::$conexion = new PDO($cadenaConexion, DB_USUARIO, DB_PASSWORD);
            self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error conectando a servidor de base de datos: " . $e->getMessage());
        }
    }


    public static function getConexion()
    {
        // Comprobamos si existe una conexión.
        if (!self::$conexion) {
            new self;
            // otra opción:
            // self::__construct();
        }

        return self::$conexion;
    }
}