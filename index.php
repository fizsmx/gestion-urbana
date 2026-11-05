<?php
session_start();

/* =========================================================
   CONEXIÓN A LA BASE DE DATOS
========================================================= */

$host = getenv("DB_HOST") ?: "localhost";
$usuario_db = getenv("DB_USER") ?: "root";
$password_db = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";
$base_datos = getenv("DB_NAME") ?: "gestion_urbana";

$conexion = new mysqli(
    $host,
    $usuario_db,
    $password_db,
    $base_datos
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipo_mensaje = "";
$reporte_consultado = null;


/* =========================================================
   FUNCIONES
========================================================= */

function es_admin()
{
    return isset($_SESSION["rol"]) &&
           $_SESSION["rol"] === "admin";
}


/* =========================================================
   SUBIR IMAGEN DE FORMA SEGURA
========================================================= */

function subir_imagen_segura($archivo, $carpeta = "uploads/")
{
    if (
        !isset($archivo) ||
        !isset($archivo["error"]) ||
        $archivo["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($archivo["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    /* Máximo 5 MB */
    if ($archivo["size"] > 5 * 1024 * 1024) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo["tmp_name"]);

    $permitidos = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
        "image/gif"  => "gif"
    ];

    if (!isset($permitidos[$mime])) {
        return false;
    }

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }

    $nombre_archivo =
        uniqid("img_", true) .
        "." .
        $permitidos[$mime];

    $ruta = $carpeta . $nombre_archivo;

    if (move_uploaded_file(
        $archivo["tmp_name"],
        $ruta
    )) {
        return $ruta;
    }

    return false;
}


/* =========================================================
   CERRAR SESIÓN
========================================================= */

if (isset($_GET["logout"])) {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit;
}


/* =========================================================
   REGISTRO DE USUARIO
========================================================= */

if (isset($_POST["registro_usuario"])) {

    $nombre = trim($_POST["nombre"] ?? "");
    $usuario = trim($_POST["usuario"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmar_password =
        $_POST["confirmar_password"] ?? "";

    if (
        $nombre === "" ||
        $usuario === "" ||
        $email === "" ||
        $password === ""
    ) {

        $mensaje =
            "Completa todos los campos.";
        $tipo_mensaje = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensaje =
            "Introduce un correo electrónico válido.";
        $tipo_mensaje = "error";

    } elseif (strlen($password) < 6) {

        $mensaje =
            "La contraseña debe tener al menos 6 caracteres.";
        $tipo_mensaje = "error";

    } elseif ($password !== $confirmar_password) {

        $mensaje =
            "Las contraseñas no coinciden.";
        $tipo_mensaje = "error";

    } else {

        /* Comprobar usuario o correo existente */

        $consulta = $conexion->prepare(
            "SELECT id FROM usuarios
             WHERE usuario = ? OR email = ?
             LIMIT 1"
        );

        $consulta->bind_param(
            "ss",
            $usuario,
            $email
        );

        $consulta->execute();
        $resultado = $consulta->get_result();

        if ($resultado->num_rows > 0) {

            $mensaje =
                "El usuario o correo ya está registrado.";
            $tipo_mensaje = "error";

        } else {

            $password_hash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            /* Todos los nuevos usuarios serán usuarios normales */

            $insertar = $conexion->prepare(
                "INSERT INTO usuarios
                (nombre, usuario, email, password, rol)
                VALUES (?, ?, ?, ?, 'usuario')"
            );

            $insertar->bind_param(
                "ssss",
                $nombre,
                $usuario,
                $email,
                $password_hash
            );

            if ($insertar->execute()) {

                $mensaje =
                    "Cuenta creada correctamente. Ahora puedes iniciar sesión.";
                $tipo_mensaje = "exito";

            } else {

                $mensaje =
                    "No se pudo crear la cuenta.";
                $tipo_mensaje = "error";
            }
        }
    }
}


/* =========================================================
   INICIO DE SESIÓN
========================================================= */

if (isset($_POST["login"])) {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($usuario === "" || $password === "") {

        $mensaje =
            "Completa usuario y contraseña.";
        $tipo_mensaje = "error";

    } else {

        $consulta = $conexion->prepare(
            "SELECT
                id,
                nombre,
                usuario,
                email,
                password,
                rol
             FROM usuarios
             WHERE usuario = ?
             LIMIT 1"
        );

        $consulta->bind_param(
            "s",
            $usuario
        );

        $consulta->execute();

        $resultado = $consulta->get_result();

        if ($resultado->num_rows === 1) {

            $usuario_encontrado =
                $resultado->fetch_assoc();

            if (
                password_verify(
                    $password,
                    $usuario_encontrado["password"]
                )
            ) {

                session_regenerate_id(true);

                $_SESSION["usuario_id"] =
                    $usuario_encontrado["id"];

                $_SESSION["usuario"] =
                    $usuario_encontrado["usuario"];

                $_SESSION["nombre"] =
                    $usuario_encontrado["nombre"];

                $_SESSION["rol"] =
                    $usuario_encontrado["rol"] ?? "usuario";

                header("Location: index.php");
                exit;

            } else {

                $mensaje =
                    "Usuario o contraseña incorrectos.";
                $tipo_mensaje = "error";
            }

        } else {

            $mensaje =
                "Usuario o contraseña incorrectos.";
            $tipo_mensaje = "error";
        }
    }
}


/* =========================================================
   REGISTRAR PROBLEMA
========================================================= */

if (isset($_POST["registrar_problema"])) {

    if (!isset($_SESSION["usuario_id"])) {

        $mensaje =
            "Debes iniciar sesión para registrar un problema.";
        $tipo_mensaje = "error";

    } else {

        $titulo =
            trim($_POST["titulo"] ?? "");

        $categoria =
            trim($_POST["categoria"] ?? "");

        $prioridad =
            trim($_POST["prioridad"] ?? "");

        $descripcion =
            trim($_POST["descripcion"] ?? "");

        $ubicacion =
            trim($_POST["ubicacion"] ?? "");

        $latitud = trim($_POST["latitud"] ?? "");
        $longitud = trim($_POST["longitud"] ?? "");
        if ($latitud !== "" && $longitud !== "" && is_numeric($latitud) && is_numeric($longitud)) {
            $ubicacion .= " | Coordenadas: " . $latitud . ", " . $longitud;
        }

        $usuario_id =
            $_SESSION["usuario_id"];

        if (
            $titulo === "" ||
            $categoria === "" ||
            $prioridad === "" ||
            $descripcion === "" ||
            $ubicacion === ""
        ) {

            $mensaje =
                "Completa todos los campos del reporte.";
            $tipo_mensaje = "error";

        } else {

            $codigo =
                "URB-" .
                strtoupper(
                    substr(
                        uniqid(),
                        -6
                    )
                );

            $estado = "Pendiente";

            /* Subir imagen del problema */

            $imagen =
                subir_imagen_segura(
                    $_FILES["imagen"] ?? null,
                    "uploads/"
                );

            if ($imagen === false) {

                $mensaje =
                    "La imagen no es válida o supera los 5 MB.";
                $tipo_mensaje = "error";

            } else {

                $insertar = $conexion->prepare(
                    "INSERT INTO problemas
                    (
                        usuario_id,
                        codigo,
                        titulo,
                        categoria,
                        prioridad,
                        descripcion,
                        ubicacion,
                        imagen,
                        estado
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $insertar->bind_param(
                    "issssssss",
                    $usuario_id,
                    $codigo,
                    $titulo,
                    $categoria,
                    $prioridad,
                    $descripcion,
                    $ubicacion,
                    $imagen,
                    $estado
                );

                if ($insertar->execute()) {

                    $mensaje =
                        "Reporte registrado correctamente. Código: " .
                        $codigo;

                    $tipo_mensaje = "exito";

                } else {

                    $mensaje =
                        "No se pudo registrar el problema.";
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}


/* =========================================================
   ADMINISTRADOR:
   CAMBIAR ESTADO + RESPONDER + IMAGEN
========================================================= */

if (isset($_POST["admin_actualizar_reporte"])) {

    if (!es_admin()) {

        $mensaje =
            "No tienes permisos para realizar esta acción.";
        $tipo_mensaje = "error";

    } else {

        $reporte_id =
            intval($_POST["reporte_id"] ?? 0);

        $nuevo_estado =
            trim($_POST["nuevo_estado"] ?? "");

        $respuesta_admin =
            trim($_POST["respuesta_admin"] ?? "");

        $estados_permitidos = [
            "Pendiente",
            "En Proceso",
            "Solucionado"
        ];

        if (
            $reporte_id <= 0 ||
            !in_array(
                $nuevo_estado,
                $estados_permitidos,
                true
            )
        ) {

            $mensaje =
                "Los datos del reporte no son válidos.";
            $tipo_mensaje = "error";

        } else {

            /* Obtener imagen anterior */

            $consulta_anterior =
                $conexion->prepare(
                    "SELECT imagen_respuesta
                     FROM problemas
                     WHERE id = ?
                     LIMIT 1"
                );

            $consulta_anterior->bind_param(
                "i",
                $reporte_id
            );

            $consulta_anterior->execute();

            $resultado_anterior =
                $consulta_anterior->get_result();

            $reporte_anterior =
                $resultado_anterior->fetch_assoc();

            $imagen_anterior =
                $reporte_anterior["imagen_respuesta"] ?? null;


            /* Imagen nueva */

            $imagen_respuesta =
                subir_imagen_segura(
                    $_FILES["imagen_respuesta"] ?? null,
                    "uploads/respuestas/"
                );

            if ($imagen_respuesta === false) {

                $mensaje =
                    "La imagen de respuesta no es válida o supera los 5 MB.";
                $tipo_mensaje = "error";

            } else {

                /*
                 Si no se subió imagen nueva,
                 conservamos la anterior.
                */

                if ($imagen_respuesta === null) {

                    $imagen_final =
                        $imagen_anterior;

                } else {

                    $imagen_final =
                        $imagen_respuesta;

                    /* Eliminar imagen anterior */

                    if (
                        !empty($imagen_anterior) &&
                        file_exists($imagen_anterior)
                    ) {

                        unlink($imagen_anterior);
                    }
                }


                /* Eliminar manualmente imagen actual */

                if (
                    isset($_POST["eliminar_imagen_respuesta"]) &&
                    $_POST["eliminar_imagen_respuesta"] === "1"
                ) {

                    if (
                        !empty($imagen_final) &&
                        file_exists($imagen_final)
                    ) {

                        unlink($imagen_final);
                    }

                    $imagen_final = null;
                }


                /* Actualizar reporte */

                $actualizar = $conexion->prepare(
                    "UPDATE problemas
                     SET
                        estado = ?,
                        respuesta_admin = ?,
                        imagen_respuesta = ?,
                        fecha_respuesta = NOW()
                     WHERE id = ?"
                );

                $actualizar->bind_param(
                    "sssi",
                    $nuevo_estado,
                    $respuesta_admin,
                    $imagen_final,
                    $reporte_id
                );

                if ($actualizar->execute()) {

                    $mensaje =
                        "Reporte actualizado correctamente. El usuario ya puede ver tu respuesta.";

                    $tipo_mensaje = "exito";

                } else {

                    $mensaje =
                        "No se pudo actualizar el reporte.";
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}


/* =========================================================
   CONSULTAR REPORTE
========================================================= */

if (isset($_POST["consultar_reporte"])) {

    $codigo =
        trim($_POST["codigo"] ?? "");

    if ($codigo === "") {

        $mensaje =
            "Introduce el código del reporte.";
        $tipo_mensaje = "error";

    } else {

        $consulta = $conexion->prepare(
            "SELECT
                p.*,
                u.nombre AS usuario_nombre
             FROM problemas p
             LEFT JOIN usuarios u
                ON p.usuario_id = u.id
             WHERE p.codigo = ?
             LIMIT 1"
        );

        $consulta->bind_param(
            "s",
            $codigo
        );

        $consulta->execute();

        $resultado =
            $consulta->get_result();

        if ($resultado->num_rows === 1) {

            $reporte_consultado =
                $resultado->fetch_assoc();

        } else {

            $mensaje =
                "No se encontró ningún reporte con ese código.";
            $tipo_mensaje = "error";
        }
    }
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$estadisticas = [
    "total" => 0,
    "pendientes" => 0,
    "proceso" => 0,
    "solucionados" => 0
];

$resultado_estadisticas =
    $conexion->query(
        "SELECT
            COUNT(*) AS total,
            SUM(estado = 'Pendiente') AS pendientes,
            SUM(estado = 'En Proceso') AS proceso,
            SUM(estado = 'Solucionado') AS solucionados
         FROM problemas"
    );

if ($resultado_estadisticas) {

    $estadisticas =
        $resultado_estadisticas->fetch_assoc();
}


/* =========================================================
   ÚLTIMOS REPORTES
========================================================= */

$ultimos_reportes =
    $conexion->query(
        "SELECT *
         FROM problemas
         ORDER BY id DESC
         LIMIT 6"
    );


/* =========================================================
   REPORTES PARA ADMINISTRADOR
========================================================= */

$reportes_admin = null;

if (es_admin()) {

    $reportes_admin =
        $conexion->query(
            "SELECT
                p.*,
                u.nombre AS usuario_nombre,
                u.usuario AS usuario_login
             FROM problemas p
             LEFT JOIN usuarios u
                ON p.usuario_id = u.id
             ORDER BY p.id DESC"
        );
}


/* =========================================================
   MIS REPORTES
========================================================= */

$mis_reportes = null;

if (isset($_SESSION["usuario_id"])) {

    $usuario_actual =
        $_SESSION["usuario_id"];

    $consulta_mis_reportes =
        $conexion->prepare(
            "SELECT *
             FROM problemas
             WHERE usuario_id = ?
             ORDER BY id DESC"
        );

    $consulta_mis_reportes->bind_param(
        "i",
        $usuario_actual
    );

    $consulta_mis_reportes->execute();

    $mis_reportes =
        $consulta_mis_reportes->get_result();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Gestión de Problemas Urbanos - Tu Barrio
</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<style>

/* =========================================================
   ESTILOS GENERALES
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f1f5f9;
    color: #1e293b;
}

a {
    text-decoration: none;
}

.container {
    width: 90%;
    max-width: 1200px;
    margin: auto;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;

    background:
        rgba(15, 23, 42, 0.97);

    box-shadow:
        0 5px 20px rgba(0,0,0,.15);
}

.navbar-content {
    min-height: 70px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;
}

.logo {
    color: white;
    font-size: 22px;
    font-weight: bold;
}

.logo span {
    color: #38bdf8;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;

    flex-wrap: wrap;
}

.nav-links a {
    color: #e2e8f0;
    padding: 10px 14px;
    border-radius: 8px;

    transition: .3s;
}

.nav-links a:hover {
    background: #2563eb;
    color: white;
}

.usuario-menu {
    color: #bae6fd;
    font-size: 14px;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    min-height: 520px;

    display: flex;
    align-items: center;

    background:
        linear-gradient(
            rgba(15,23,42,.72),
            rgba(15,23,42,.72)
        ),
        url("https://images.unsplash.com/photo-1449824913935-59a10b8d2000?auto=format&fit=crop&w=1600&q=80");

    background-size: cover;
    background-position: center;
}

.hero-content {
    color: white;
    max-width: 750px;
}

.hero h1 {
    font-size: clamp(38px, 6vw, 65px);
    margin-bottom: 20px;
    line-height: 1.05;
}

.hero h1 span {
    color: #38bdf8;
}

.hero p {
    font-size: 20px;
    line-height: 1.7;
    margin-bottom: 30px;
}

.btn {
    display: inline-block;

    border: none;
    cursor: pointer;

    padding: 13px 22px;
    border-radius: 10px;

    font-weight: bold;
    font-size: 15px;

    transition: .3s;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
}

.btn-success {
    background: #16a34a;
    color: white;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}


/* =========================================================
   SECCIONES
========================================================= */

section {
    padding: 75px 0;
}

.section-title {
    text-align: center;
    margin-bottom: 45px;
}

.section-title h2 {
    font-size: 35px;
    margin-bottom: 12px;
    color: #0f172a;
}

.section-title p {
    color: #64748b;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.stats {
    margin-top: -55px;
    position: relative;
    z-index: 10;
}

.stats-grid {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;
}

.stat-card {
    background: white;
    padding: 28px;
    border-radius: 18px;

    text-align: center;

    box-shadow:
        0 15px 35px rgba(15,23,42,.12);
}

.stat-card h3 {
    font-size: 38px;
    color: #2563eb;
    margin-bottom: 8px;
}

.stat-card p {
    color: #64748b;
}


/* =========================================================
   TARJETAS
========================================================= */

.cards {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 25px;
}

.card {
    background: white;
    border-radius: 18px;
    padding: 30px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.08);

    transition: .3s;
}

.card:hover {
    transform: translateY(-5px);

    box-shadow:
        0 15px 35px rgba(0,0,0,.12);
}

.card-icon {
    font-size: 42px;
    margin-bottom: 18px;
}

.card h3 {
    margin-bottom: 12px;
}

.card p {
    color: #64748b;
    line-height: 1.7;
}


/* =========================================================
   FORMULARIOS
========================================================= */

.form-container {
    max-width: 750px;
    margin: auto;

    background: white;

    padding: 35px;

    border-radius: 20px;

    box-shadow:
        0 15px 35px rgba(0,0,0,.08);
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-control {
    width: 100%;

    padding: 13px 15px;

    border: 1px solid #cbd5e1;

    border-radius: 10px;

    font-size: 15px;

    outline: none;
}

.form-control:focus {
    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.1);
}

textarea.form-control {
    min-height: 130px;
    resize: vertical;
}

.form-grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 18px;
}


/* =========================================================
   MENSAJES
========================================================= */

.mensaje {
    position: fixed;

    top: 85px;
    right: 20px;

    z-index: 2000;

    max-width: 420px;

    padding: 18px 22px;

    border-radius: 12px;

    font-weight: bold;

    box-shadow:
        0 10px 30px rgba(0,0,0,.15);
}

.mensaje.exito {
    background: #dcfce7;
    color: #166534;
}

.mensaje.error {
    background: #fee2e2;
    color: #991b1b;
}


/* =========================================================
   ESTADOS
========================================================= */

.estado {
    display: inline-block;

    padding: 7px 15px;

    border-radius: 30px;

    font-size: 13px;

    font-weight: bold;
}

.pendiente {
    background: #fee2e2;
    color: #b91c1c;
}

.proceso {
    background: #fef3c7;
    color: #92400e;
}

.solucionado {
    background: #dcfce7;
    color: #166534;
}


/* =========================================================
   REPORTES
========================================================= */

.reportes-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 25px;
}

.reporte-card {
    background: white;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 10px 30px rgba(0,0,0,.08);
}

.reporte-imagen {
    width: 100%;
    height: 210px;
    object-fit: cover;
}

.reporte-contenido {
    padding: 22px;
}

.reporte-contenido h3 {
    margin-bottom: 12px;
}

.reporte-dato {
    margin: 8px 0;
    color: #64748b;
}


/* =========================================================
   CONSULTA
========================================================= */

.consulta {
    max-width: 800px;
    margin: auto;
}

.resultado-reporte {
    margin-top: 30px;

    background: white;

    padding: 30px;

    border-radius: 18px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.08);
}

.resultado-reporte img {
    max-width: 100%;
    max-height: 400px;

    object-fit: contain;

    border-radius: 14px;

    margin-top: 15px;
}


/* =========================================================
   RESPUESTA DEL ADMINISTRADOR
========================================================= */

.respuesta-admin {
    margin-top: 25px;

    padding: 25px;

    border-radius: 16px;

    background: #eff6ff;

    border-left:
        5px solid #2563eb;
}

.respuesta-admin h4 {
    margin-bottom: 12px;

    color: #1d4ed8;

    font-size: 20px;
}

.respuesta-admin p {
    line-height: 1.7;

    color: #334155;
}

.respuesta-admin img {
    display: block;

    max-width: 100%;
    max-height: 350px;

    border-radius: 12px;

    margin-top: 15px;
}

.respuesta-fecha {
    display: block;

    margin-top: 12px;

    color: #64748b;

    font-size: 13px;
}

.sin-respuesta {
    margin-top: 20px;

    padding: 18px;

    border-radius: 12px;

    background: #f8fafc;

    color: #64748b;
}


/* =========================================================
   MIS REPORTES
========================================================= */

.mi-reporte {
    background: white;

    padding: 25px;

    margin-bottom: 20px;

    border-radius: 18px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.07);
}

.mi-reporte h3 {
    margin-bottom: 10px;
}

.mi-reporte-info {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-top: 15px;
}

.mi-reporte img {
    max-width: 300px;

    max-height: 220px;

    border-radius: 12px;

    margin-top: 15px;
}


/* =========================================================
   PANEL ADMIN
========================================================= */

.admin-section {
    background: #e2e8f0;
}

.admin-card {
    background: white;

    border-radius: 20px;

    padding: 28px;

    margin-bottom: 28px;

    box-shadow:
        0 12px 35px rgba(0,0,0,.08);
}

.admin-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;
}

.admin-header h3 {
    color: #0f172a;
}

.admin-info {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 25px;
}

.admin-col {
    padding: 20px;

    background: #f8fafc;

    border-radius: 15px;
}

.admin-col h4 {
    margin-bottom: 15px;

    color: #1e293b;
}

.admin-imagen {
    width: 100%;

    max-height: 320px;

    object-fit: cover;

    border-radius: 12px;

    margin-top: 12px;
}

.respuesta-preview {
    width: 100%;

    max-height: 300px;

    object-fit: contain;

    border-radius: 12px;

    margin-top: 12px;
}


/* =========================================================
   FOOTER
========================================================= */

footer {
    background: #0f172a;

    color: #cbd5e1;

    padding: 45px 0;

    text-align: center;
}

footer h3 {
    color: white;

    margin-bottom: 10px;
}

footer p {
    margin: 6px 0;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 900px) {

    .stats-grid,
    .cards,
    .reportes-grid {
        grid-template-columns: 1fr 1fr;
    }

    .admin-info {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 650px) {

    .navbar-content {
        flex-direction: column;

        padding: 15px 0;
    }

    .nav-links {
        justify-content: center;
    }

    .hero {
        min-height: 450px;
    }

    .stats {
        margin-top: 20px;
    }

    .stats-grid,
    .cards,
    .reportes-grid,
    .form-grid,
    .mi-reporte-info {
        grid-template-columns: 1fr;
    }

    section {
        padding: 55px 0;
    }

    .form-container {
        padding: 22px;
    }
}

/* =========================================================
   ESTÉTICA GESTIÓN URBANA: VERDE, AZUL MARINO Y BLANCO
   Inspirada en la imagen de referencia del proyecto.
========================================================= */
:root {
    --azul-noche: #071b3b;
    --azul: #0757a6;
    --verde: #08a765;
    --verde-oscuro: #078653;
    --celeste: #1f9bd1;
    --fondo: #f5f9fc;
    --texto: #102746;
    --menta: #e4f7ef;
    --azul-claro: #e5f1ff;
    --borde: #d8e6ef;
}
body { background:var(--fondo); color:var(--texto); line-height:1.65; }
a { text-decoration:none; }
.container { width:min(1160px, 92%); }
.navbar { background:rgba(7,27,59,.98); backdrop-filter:blur(12px); box-shadow:0 8px 24px rgba(7,27,59,.12); }
.navbar-content { min-height:76px; }
.logo { color:#fff; letter-spacing:-.5px; font-size:21px; line-height:1.25; }
.logo span { display:inline; color:#19c779; }
.nav-links { gap:5px; }
.nav-links a { font-size:14px; padding:10px 13px; border-radius:9px; transition:all .2s ease; }
.nav-links a:hover { background:var(--verde); color:#fff; }
.usuario-menu { color:#c8f7e1; }
.hero {
    min-height:470px;
    background-image:linear-gradient(90deg,rgba(5,23,51,.88),rgba(7,38,67,.65)),url("https://images.unsplash.com/photo-1449824913935-59a10b8d2000?auto=format&fit=crop&w=1800&q=85");
    background-position:center 54%;
    border-bottom:5px solid var(--verde);
}
.hero-content { max-width:780px; padding-top:12px; }
.hero h1 { color:#fff; font-size:clamp(36px,5.2vw,61px); letter-spacing:-1.8px; line-height:1.08; }
.hero h1 span { color:#27d184; }
.hero p { max-width:680px; font-size:18px; color:#edf8ff; }
.btn { border-radius:10px; box-shadow:0 6px 15px rgba(7,27,59,.12); font-weight:700; transition:transform .2s,background .2s; }
.btn:hover { transform:translateY(-2px); }
.btn-primary { background:var(--verde); color:#fff; }
.btn-primary:hover, .btn-success:hover { background:var(--verde-oscuro); }
.btn-success { background:var(--verde); color:#fff; }
.btn-warning { background:#f59e0b; color:#fff; }
.btn-danger { background:#d94848; color:#fff; }
section { padding:68px 0; }
.section-title { margin-bottom:36px; }
.section-title h2 { color:var(--azul-noche); font-size:clamp(27px,3vw,36px); letter-spacing:-.7px; }
.section-title p { color:#58708b; font-size:15px; }
.stats { margin-top:-42px; }
.stats-grid { gap:17px; }
.stat-card { background:#fff; border:1px solid #e0eaf1; border-top:4px solid var(--verde); border-radius:17px; padding:25px 18px; box-shadow:0 12px 28px rgba(7,27,59,.08); }
.stat-card h3 { color:var(--verde); }
.cards { gap:19px; }
.card { background:#fff; border:1px solid #dfebf1; border-radius:17px; box-shadow:0 10px 26px rgba(7,27,59,.05); padding:26px; transition:transform .2s,box-shadow .2s; }
.card:hover { transform:translateY(-3px); box-shadow:0 15px 32px rgba(7,27,59,.1); }
.form-container { background:#fff; border:1px solid #dce9ef; border-top:5px solid var(--verde); border-radius:20px; box-shadow:0 18px 42px rgba(7,27,59,.07); padding:clamp(22px,4vw,38px); }
.form-control { width:100%; border:1px solid #cedde7; border-radius:10px; background:#fbfdff; color:var(--texto); padding:12px 14px; outline:none; transition:border .2s,box-shadow .2s; }
.form-control:focus { background:#fff; border-color:var(--verde); box-shadow:0 0 0 4px rgba(8,167,101,.13); }
.form-group label { color:var(--azul-noche); font-size:14px; font-weight:700; display:block; margin-bottom:7px; }
.help-text { display:block; margin-top:7px; color:#60758b; font-size:13px; }
.map-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin:12px 0; }
.map-actions .btn { padding:10px 15px; font-size:13px; }
#mapa-ubicacion { height:320px; width:100%; overflow:hidden; border:2px solid #bfe8d5; border-radius:14px; z-index:1; background:#e4f7ef; }
#coordenadas-texto { color:var(--verde-oscuro); font-weight:600; }
footer { background:var(--azul-noche); color:#e9f3ff; border-top:5px solid var(--verde); }
/* Paneles y tablas mantienen el mismo lenguaje visual */
table { border-collapse:collapse; }
th { background:#e4f7ef; color:var(--azul-noche); }
input[type="file"] { max-width:100%; }
@media(max-width:760px) {
 .navbar-content { padding:14px 0; align-items:flex-start; flex-direction:column; gap:10px; }
 .nav-links { width:100%; gap:3px; }
 .nav-links a { padding:8px 10px; font-size:13px; }
 .hero { min-height:430px; }
 .hero p { font-size:16px; }
 .stats { margin-top:20px; }
 #mapa-ubicacion { height:260px; }
}
</style>

</head>


<body>


<!-- =====================================================
     MENSAJE
====================================================== -->

<?php if ($mensaje !== ""): ?>

<div class="mensaje <?= htmlspecialchars($tipo_mensaje) ?>">

    <?= htmlspecialchars($mensaje) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     NAVBAR
====================================================== -->

<nav class="navbar">

<div class="container navbar-content">

    <a href="index.php" class="logo">
        Gestión Urbana
        <span>Tu Barrio al Día</span>
    </a>

    <div class="nav-links">

        <a href="#inicio">Inicio</a>

        <a href="#reportar">Reportar</a>

        <a href="#consultar">Consultar</a>

        <?php if (isset($_SESSION["usuario_id"])): ?>

            <a href="#mis-reportes">
                Mis reportes
            </a>

        <?php endif; ?>

        <?php if (es_admin()): ?>

            <a href="#administracion">
                ⚙️ Administración
            </a>

        <?php endif; ?>


        <?php if (isset($_SESSION["usuario_id"])): ?>

            <span class="usuario-menu">
                👤
                <?= htmlspecialchars($_SESSION["nombre"]) ?>
            </span>

            <a href="index.php?logout=1">
                Salir
            </a>

        <?php else: ?>

            <a href="#login">
                Ingresar
            </a>

            <a href="#registro">
                Registrarse
            </a>

        <?php endif; ?>

    </div>

</div>

</nav>


<!-- =====================================================
     HERO
====================================================== -->

<section class="hero" id="inicio">

<div class="container hero-content">

    <h1>
        Reporta los problemas urbanos
        <span>de tu barrio.</span>
    </h1>

    <p>
        Ayúdanos a mejorar nuestra ciudad.
        Registra problemas urbanos, consulta su estado
        y recibe respuestas de la administración.
    </p>

    <a
        href="#reportar"
        class="btn btn-primary"
    >
        📢 Reportar un problema
    </a>

</div>

</section>


<!-- =====================================================
     ESTADÍSTICAS
====================================================== -->

<section class="stats">

<div class="container">

<div class="stats-grid">

    <div class="stat-card">

        <h3>
            <?= intval($estadisticas["total"]) ?>
        </h3>

        <p>
            Reportes registrados
        </p>

    </div>


    <div class="stat-card">

        <h3>
            <?= intval($estadisticas["pendientes"]) ?>
        </h3>

        <p>
            Pendientes
        </p>

    </div>


    <div class="stat-card">

        <h3>
            <?= intval($estadisticas["proceso"]) ?>
        </h3>

        <p>
            En proceso
        </p>

    </div>


    <div class="stat-card">

        <h3>
            <?= intval($estadisticas["solucionados"]) ?>
        </h3>

        <p>
            Solucionados
        </p>

    </div>

</div>

</div>

</section>


<!-- =====================================================
     COMO FUNCIONA
====================================================== -->

<section>

<div class="container">

<div class="section-title">

    <h2>¿Cómo funciona?</h2>

    <p>
        Participa en la mejora de tu comunidad
    </p>

</div>


<div class="cards">

    <div class="card">

        <div class="card-icon">
            📸
        </div>

        <h3>
            1. Reporta
        </h3>

        <p>
            Registra un problema urbano,
            describe lo ocurrido y agrega
            una fotografía como evidencia.
        </p>

    </div>


    <div class="card">

        <div class="card-icon">
            🔎
        </div>

        <h3>
            2. Consulta
        </h3>

        <p>
            Obtén un código de seguimiento
            para consultar el estado de tu reporte.
        </p>

    </div>


    <div class="card">

        <div class="card-icon">
            💬
        </div>

        <h3>
            3. Recibe respuesta
        </h3>

        <p>
            La administración puede responder
            tu reporte, cambiar su estado y
            enviarte una imagen.
        </p>

    </div>

</div>

</div>

</section>


<!-- =====================================================
     LOGIN
====================================================== -->

<?php if (!isset($_SESSION["usuario_id"])): ?>

<section id="login">

<div class="container">

<div class="section-title">

    <h2>Iniciar sesión</h2>

    <p>
        Ingresa para registrar y administrar tus reportes.
    </p>

</div>


<div class="form-container">

<form method="POST">

    <div class="form-group">

        <label>
            Usuario
        </label>

        <input
            type="text"
            name="usuario"
            class="form-control"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Contraseña
        </label>

        <input
            type="password"
            name="password"
            class="form-control"
            required
        >

    </div>


    <button
        type="submit"
        name="login"
        class="btn btn-primary"
    >
        🔐 Iniciar sesión
    </button>

</form>

</div>

</div>

</section>


<!-- =====================================================
     REGISTRO
====================================================== -->

<section id="registro">

<div class="container">

<div class="section-title">

    <h2>Crear una cuenta</h2>

    <p>
        Regístrate para comenzar a reportar problemas urbanos.
    </p>

</div>


<div class="form-container">

<form method="POST">

    <div class="form-grid">

        <div class="form-group">

            <label>
                Nombre completo
            </label>

            <input
                type="text"
                name="nombre"
                class="form-control"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Usuario
            </label>

            <input
                type="text"
                name="usuario"
                class="form-control"
                required
            >

        </div>

    </div>


    <div class="form-group">

        <label>
            Correo electrónico
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            required
        >

    </div>


    <div class="form-grid">

        <div class="form-group">

            <label>
                Contraseña
            </label>

            <input
                type="password"
                name="password"
                class="form-control"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Confirmar contraseña
            </label>

            <input
                type="password"
                name="confirmar_password"
                class="form-control"
                required
            >

        </div>

    </div>


    <button
        type="submit"
        name="registro_usuario"
        class="btn btn-success"
    >
        👤 Crear cuenta
    </button>

</form>

</div>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     REPORTAR PROBLEMA
====================================================== -->

<section id="reportar">

<div class="container">

<div class="section-title">

    <h2>Registrar problema urbano</h2>

    <p>
        Cuéntanos qué está ocurriendo en tu barrio.
    </p>

</div>


<?php if (isset($_SESSION["usuario_id"])): ?>

<div class="form-container">

<form
    method="POST"
    enctype="multipart/form-data"
>


    <div class="form-group">

        <label>
            Título del problema
        </label>

        <input
            type="text"
            name="titulo"
            class="form-control"
            placeholder="Ej.: Calle con muchos baches"
            required
        >

    </div>


    <div class="form-grid">

        <div class="form-group">

            <label>
                Categoría
            </label>

            <select
                name="categoria"
                class="form-control"
                required
            >

                <option value="">
                    Seleccionar
                </option>

                <option>
                    Alumbrado público
                </option>

                <option>
                    Calles y baches
                </option>

                <option>
                    Basura
                </option>

                <option>
                    Alcantarillado
                </option>

                <option>
                    Agua
                </option>

                <option>
                    Seguridad
                </option>

                <option>
                    Parques
                </option>

                <option>
                    Otros
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>
                Prioridad
            </label>

            <select
                name="prioridad"
                class="form-control"
                required
            >

                <option value="">
                    Seleccionar
                </option>

                <option>
                    Baja
                </option>

                <option>
                    Media
                </option>

                <option>
                    Alta
                </option>

                <option>
                    Urgente
                </option>

            </select>

        </div>

    </div>


    <div class="form-group">

        <label>
            Ubicación
        </label>

        <input
            type="text"
            id="ubicacion"
            name="ubicacion"
            class="form-control"
            placeholder="Ej.: Barrio, calle o avenida, referencia cercana"
            required
        >
        <small class="help-text">Escribe una dirección o marca el punto exacto en el mapa.</small>
        <div class="map-actions">
            <button type="button" class="btn btn-primary" id="usar-mi-ubicacion">📍 Usar mi ubicación</button>
            <span id="coordenadas-texto" class="help-text">Aún no seleccionaste un punto en el mapa.</span>
        </div>
        <div id="mapa-ubicacion" role="application" aria-label="Mapa para seleccionar la ubicación del problema"></div>
        <input type="hidden" name="latitud" id="latitud">
        <input type="hidden" name="longitud" id="longitud">

    </div>


    <div class="form-group">

        <label>
            Descripción
        </label>

        <textarea
            name="descripcion"
            class="form-control"
            placeholder="Describe detalladamente el problema..."
            required
        ></textarea>

    </div>


    <div class="form-group">

        <label>
            Fotografía del problema
        </label>

        <input
            type="file"
            name="imagen"
            class="form-control"
            accept="image/jpeg,image/png,image/webp,image/gif"
        >

        <small>
            Máximo 5 MB.
        </small>

    </div>


    <button
        type="submit"
        name="registrar_problema"
        class="btn btn-primary"
    >
        📢 Enviar reporte
    </button>

</form>

</div>

<?php else: ?>

<div class="form-container">

    <h3>
        🔐 Debes iniciar sesión
    </h3>

    <p style="margin:15px 0;">
        Para registrar un problema necesitas tener
        una cuenta.
    </p>

    <a
        href="#login"
        class="btn btn-primary"
    >
        Iniciar sesión
    </a>

</div>

<?php endif; ?>

</div>

</section>


<!-- =====================================================
     CONSULTAR REPORTE
====================================================== -->

<section id="consultar">

<div class="container">

<div class="section-title">

    <h2>
        Consultar reporte
    </h2>

    <p>
        Introduce el código de tu reporte.
    </p>

</div>


<div class="consulta">

<div class="form-container">

<form method="POST">

    <div class="form-group">

        <label>
            Código del reporte
        </label>

        <input
            type="text"
            name="codigo"
            class="form-control"
            placeholder="Ej.: URB-A1B2C3"
            required
        >

    </div>


    <button
        type="submit"
        name="consultar_reporte"
        class="btn btn-primary"
    >
        🔎 Consultar
    </button>

</form>

</div>


<?php if ($reporte_consultado): ?>

<div class="resultado-reporte">

    <h2>
        <?= htmlspecialchars(
            $reporte_consultado["titulo"]
        ) ?>
    </h2>


    <p class="reporte-dato">

        <strong>Código:</strong>

        <?= htmlspecialchars(
            $reporte_consultado["codigo"]
        ) ?>

    </p>


    <p class="reporte-dato">

        <strong>Categoría:</strong>

        <?= htmlspecialchars(
            $reporte_consultado["categoria"]
        ) ?>

    </p>


    <p class="reporte-dato">

        <strong>Prioridad:</strong>

        <?= htmlspecialchars(
            $reporte_consultado["prioridad"]
        ) ?>

    </p>


    <p class="reporte-dato">

        <strong>Ubicación:</strong>

        <?= htmlspecialchars(
            $reporte_consultado["ubicacion"]
        ) ?>

    </p>


    <p class="reporte-dato">

        <strong>Descripción:</strong><br>

        <?= nl2br(
            htmlspecialchars(
                $reporte_consultado["descripcion"]
            )
        ) ?>

    </p>


    <br>


    <?php

    $estado_clase = "pendiente";

    if (
        $reporte_consultado["estado"]
        === "En Proceso"
    ) {
        $estado_clase = "proceso";
    }

    if (
        $reporte_consultado["estado"]
        === "Solucionado"
    ) {
        $estado_clase = "solucionado";
    }

    ?>


    <p>

        <strong>Estado:</strong>

        <span
            class="estado <?= $estado_clase ?>"
        >
            <?= htmlspecialchars(
                $reporte_consultado["estado"]
            ) ?>
        </span>

    </p>


    <?php if (
        !empty(
            $reporte_consultado["imagen"]
        ) &&
        file_exists(
            $reporte_consultado["imagen"]
        )
    ): ?>

        <br>

        <strong>
            Imagen del problema:
        </strong>

        <img
            src="<?= htmlspecialchars(
                $reporte_consultado["imagen"]
            ) ?>"
            alt="Imagen del problema"
        >

    <?php endif; ?>


    <!-- =================================================
         RESPUESTA DEL ADMINISTRADOR
    ================================================== -->

    <?php if (
        !empty(
            $reporte_consultado["respuesta_admin"]
        )
    ): ?>

    <div class="respuesta-admin">

        <h4>
            💬 Respuesta de administración
        </h4>


        <p>

            <?= nl2br(
                htmlspecialchars(
                    $reporte_consultado[
                        "respuesta_admin"
                    ]
                )
            ) ?>

        </p>


        <?php if (
            !empty(
                $reporte_consultado[
                    "imagen_respuesta"
                ]
            ) &&
            file_exists(
                $reporte_consultado[
                    "imagen_respuesta"
                ]
            )
        ): ?>

            <img
                src="<?= htmlspecialchars(
                    $reporte_consultado[
                        "imagen_respuesta"
                    ]
                ) ?>"
                alt="Imagen enviada por administración"
            >

        <?php endif; ?>


        <?php if (
            !empty(
                $reporte_consultado[
                    "fecha_respuesta"
                ]
            )
        ): ?>

            <span class="respuesta-fecha">

                📅 Respondido el:

                <?= date(
                    "d/m/Y H:i",
                    strtotime(
                        $reporte_consultado[
                            "fecha_respuesta"
                        ]
                    )
                ) ?>

            </span>

        <?php endif; ?>

    </div>

    <?php else: ?>

    <div class="sin-respuesta">

        ⏳
        <strong>
            Aún no hay respuesta de administración.
        </strong>

        <br>

        Tu reporte está siendo revisado.

    </div>

    <?php endif; ?>


</div>

<?php endif; ?>

</div>

</div>

</section>


<!-- =====================================================
     MIS REPORTES
====================================================== -->

<?php if (isset($_SESSION["usuario_id"])): ?>

<section id="mis-reportes">

<div class="container">

<div class="section-title">

    <h2>
        Mis reportes
    </h2>

    <p>
        Aquí puedes revisar el estado y las respuestas
        de administración.
    </p>

</div>


<?php if (
    $mis_reportes &&
    $mis_reportes->num_rows > 0
): ?>


<?php while (
    $reporte = $mis_reportes->fetch_assoc()
): ?>


<div class="mi-reporte">

    <h3>
        <?= htmlspecialchars(
            $reporte["titulo"]
        ) ?>
    </h3>


    <p>

        <strong>
            Código:
        </strong>

        <?= htmlspecialchars(
            $reporte["codigo"]
        ) ?>

    </p>


    <div class="mi-reporte-info">

        <div>

            <strong>
                Categoría:
            </strong>

            <?= htmlspecialchars(
                $reporte["categoria"]
            ) ?>

        </div>


        <div>

            <strong>
                Ubicación:
            </strong>

            <?= htmlspecialchars(
                $reporte["ubicacion"]
            ) ?>

        </div>

    </div>


    <br>


    <?php

    $estado_clase =
        "pendiente";

    if (
        $reporte["estado"]
        === "En Proceso"
    ) {
        $estado_clase =
            "proceso";
    }

    if (
        $reporte["estado"]
        === "Solucionado"
    ) {
        $estado_clase =
            "solucionado";
    }

    ?>


    <span
        class="estado <?= $estado_clase ?>"
    >
        <?= htmlspecialchars(
            $reporte["estado"]
        ) ?>
    </span>


    <?php if (
        !empty(
            $reporte["respuesta_admin"]
        )
    ): ?>

    <div class="respuesta-admin">

        <h4>
            💬 Respuesta de administración
        </h4>

        <p>

            <?= nl2br(
                htmlspecialchars(
                    $reporte[
                        "respuesta_admin"
                    ]
                )
            ) ?>

        </p>


        <?php if (
            !empty(
                $reporte[
                    "imagen_respuesta"
                ]
            ) &&
            file_exists(
                $reporte[
                    "imagen_respuesta"
                ]
            )
        ): ?>

        <img
            src="<?= htmlspecialchars(
                $reporte[
                    "imagen_respuesta"
                ]
            ) ?>"
            alt="Respuesta de administración"
        >

        <?php endif; ?>


        <?php if (
            !empty(
                $reporte[
                    "fecha_respuesta"
                ]
            )
        ): ?>

        <span class="respuesta-fecha">

            📅
            <?= date(
                "d/m/Y H:i",
                strtotime(
                    $reporte[
                        "fecha_respuesta"
                    ]
                )
            ) ?>

        </span>

        <?php endif; ?>

    </div>

    <?php else: ?>

    <div class="sin-respuesta">

        ⏳
        Todavía no existe una respuesta
        de administración.

    </div>

    <?php endif; ?>


</div>


<?php endwhile; ?>


<?php else: ?>

<div class="form-container">

    <h3>
        📭 No tienes reportes todavía.
    </h3>

    <p style="margin-top:10px;">
        Cuando registres un problema,
        aparecerá aquí su estado y la respuesta
        de administración.
    </p>

</div>

<?php endif; ?>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     ÚLTIMOS REPORTES
====================================================== -->

<section>

<div class="container">

<div class="section-title">

    <h2>
        Últimos reportes
    </h2>

    <p>
        Algunos de los problemas registrados recientemente.
    </p>

</div>


<div class="reportes-grid">


<?php if (
    $ultimos_reportes &&
    $ultimos_reportes->num_rows > 0
): ?>


<?php while (
    $reporte = $ultimos_reportes->fetch_assoc()
): ?>


<div class="reporte-card">


<?php if (
    !empty($reporte["imagen"]) &&
    file_exists($reporte["imagen"])
): ?>

<img
    class="reporte-imagen"
    src="<?= htmlspecialchars(
        $reporte["imagen"]
    ) ?>"
    alt="Problema urbano"
>

<?php else: ?>

<div
    class="reporte-imagen"
    style="
        display:flex;
        align-items:center;
        justify-content:center;
        background:#e2e8f0;
        font-size:60px;
    "
>
    🏙️
</div>

<?php endif; ?>


<div class="reporte-contenido">

    <h3>
        <?= htmlspecialchars(
            $reporte["titulo"]
        ) ?>
    </h3>


    <p class="reporte-dato">

        📍
        <?= htmlspecialchars(
            $reporte["ubicacion"]
        ) ?>

    </p>


    <?php

    $estado_clase =
        "pendiente";

    if (
        $reporte["estado"]
        === "En Proceso"
    ) {
        $estado_clase =
            "proceso";
    }

    if (
        $reporte["estado"]
        === "Solucionado"
    ) {
        $estado_clase =
            "solucionado";
    }

    ?>


    <span
        class="estado <?= $estado_clase ?>"
    >
        <?= htmlspecialchars(
            $reporte["estado"]
        ) ?>
    </span>

</div>

</div>


<?php endwhile; ?>


<?php else: ?>

<p>
    Todavía no existen reportes.
</p>

<?php endif; ?>


</div>

</div>

</section>


<!-- =====================================================
     PANEL DE ADMINISTRACIÓN
====================================================== -->

<?php if (es_admin()): ?>

<section
    id="administracion"
    class="admin-section"
>

<div class="container">

<div class="section-title">

    <h2>
        ⚙️ Panel de Administración
    </h2>

    <p>
        Gestiona los reportes, cambia su estado
        y responde a los ciudadanos.
    </p>

</div>


<?php if (
    $reportes_admin &&
    $reportes_admin->num_rows > 0
): ?>


<?php while (
    $reporte = $reportes_admin->fetch_assoc()
): ?>


<div class="admin-card">

<div class="admin-header">

    <h3>

        <?= htmlspecialchars(
            $reporte["titulo"]
        ) ?>

    </h3>


    <?php

    $estado_clase =
        "pendiente";

    if (
        $reporte["estado"]
        === "En Proceso"
    ) {
        $estado_clase =
            "proceso";
    }

    if (
        $reporte["estado"]
        === "Solucionado"
    ) {
        $estado_clase =
            "solucionado";
    }

    ?>


    <span
        class="estado <?= $estado_clase ?>"
    >

        <?= htmlspecialchars(
            $reporte["estado"]
        ) ?>

    </span>

</div>


<div class="admin-info">


<!-- =================================================
     INFORMACIÓN DEL REPORTE
================================================== -->

<div class="admin-col">

    <h4>
        📋 Información del reporte
    </h4>


    <p>
        <strong>Código:</strong>
        <?= htmlspecialchars(
            $reporte["codigo"]
        ) ?>
    </p>


    <p>
        <strong>Usuario:</strong>
        <?= htmlspecialchars(
            $reporte["usuario_nombre"]
            ?? "Usuario"
        ) ?>
    </p>


    <p>
        <strong>Categoría:</strong>
        <?= htmlspecialchars(
            $reporte["categoria"]
        ) ?>
    </p>


    <p>
        <strong>Prioridad:</strong>
        <?= htmlspecialchars(
            $reporte["prioridad"]
        ) ?>
    </p>


    <p>
        <strong>Ubicación:</strong>
        <?= htmlspecialchars(
            $reporte["ubicacion"]
        ) ?>
    </p>


    <br>


    <strong>
        Descripción:
    </strong>


    <p style="margin-top:8px;line-height:1.6;">

        <?= nl2br(
            htmlspecialchars(
                $reporte["descripcion"]
            )
        ) ?>

    </p>


    <?php if (
        !empty($reporte["imagen"]) &&
        file_exists($reporte["imagen"])
    ): ?>

    <img
        class="admin-imagen"
        src="<?= htmlspecialchars(
            $reporte["imagen"]
        ) ?>"
        alt="Imagen del problema"
    >

    <?php endif; ?>

</div>


<!-- =================================================
     RESPONDER AL USUARIO
================================================== -->

<div class="admin-col">

    <h4>
        💬 Responder al usuario
    </h4>


<form
    method="POST"
    enctype="multipart/form-data"
>


    <input
        type="hidden"
        name="reporte_id"
        value="<?= intval(
            $reporte["id"]
        ) ?>"
    >


    <div class="form-group">

        <label>
            Estado del reporte
        </label>

        <select
            name="nuevo_estado"
            class="form-control"
            required
        >

            <option
                value="Pendiente"
                <?= $reporte["estado"] === "Pendiente"
                    ? "selected"
                    : "" ?>
            >
                Pendiente
            </option>


            <option
                value="En Proceso"
                <?= $reporte["estado"] === "En Proceso"
                    ? "selected"
                    : "" ?>
            >
                En Proceso
            </option>


            <option
                value="Solucionado"
                <?= $reporte["estado"] === "Solucionado"
                    ? "selected"
                    : "" ?>
            >
                Solucionado
            </option>

        </select>

    </div>


    <div class="form-group">

        <label>
            Respuesta al usuario
        </label>

        <textarea
            name="respuesta_admin"
            class="form-control"
            placeholder="Escribe aquí la respuesta..."
        ><?= htmlspecialchars(
            $reporte["respuesta_admin"] ?? ""
        ) ?></textarea>

    </div>


    <div class="form-group">

        <label>
            📷 Imagen para el usuario
        </label>

        <input
            type="file"
            name="imagen_respuesta"
            class="form-control"
            accept="image/jpeg,image/png,image/webp,image/gif"
        >

        <small>
            Máximo 5 MB.
        </small>

    </div>


    <?php if (
        !empty(
            $reporte["imagen_respuesta"]
        ) &&
        file_exists(
            $reporte["imagen_respuesta"]
        )
    ): ?>

        <p>
            <strong>
                Imagen actual:
            </strong>
        </p>

        <img
            class="respuesta-preview"
            src="<?= htmlspecialchars(
                $reporte["imagen_respuesta"]
            ) ?>"
            alt="Respuesta actual"
        >


        <label
            style="
                display:block;
                margin-top:12px;
            "
        >

            <input
                type="checkbox"
                name="eliminar_imagen_respuesta"
                value="1"
            >

            Eliminar imagen actual

        </label>

    <?php endif; ?>


    <br>


    <button
        type="submit"
        name="admin_actualizar_reporte"
        class="btn btn-primary"
    >
        💾 Guardar respuesta
    </button>

</form>

</div>

</div>

</div>


<?php endwhile; ?>


<?php else: ?>

<div class="admin-card">

    <h3>
        📭 No existen reportes.
    </h3>

</div>

<?php endif; ?>

</div>

</section>

<?php endif; ?>


<!-- =====================================================
     FOOTER
====================================================== -->

<footer>

<div class="container">

    <h3>
        Gestión Urbana
    </h3>

    <p>
        Tu Barrio al Día
    </p>

    <p>
        Plataforma web para la gestión
        de problemas urbanos.
    </p>

    <p>
        © 2026 Gestión Urbana
    </p>

</div>

</footer>


<script>

/* =========================================================
   OCULTAR MENSAJE AUTOMÁTICAMENTE
========================================================= */

setTimeout(function() {

    const mensaje =
        document.querySelector(".mensaje");

    if (mensaje) {

        mensaje.style.opacity = "0";

        mensaje.style.transition =
            "opacity .5s";

        setTimeout(function() {

            mensaje.remove();

        }, 500);
    }

}, 5000);


/* =========================================================
   SCROLL SUAVE
========================================================= */

document.querySelectorAll(
    'a[href^="#"]'
).forEach(function(enlace) {

    enlace.addEventListener(
        "click",
        function(e) {

            const objetivo =
                document.querySelector(
                    this.getAttribute("href")
                );

            if (objetivo) {

                e.preventDefault();

                objetivo.scrollIntoView({
                    behavior: "smooth"
                });

            }

        }
    );

});

/* =========================================================
   MAPA PARA ELEGIR LA UBICACIÓN DEL REPORTE
========================================================= */
(function () {
    const mapElement = document.getElementById('mapa-ubicacion');
    if (!mapElement || typeof L === 'undefined') return;

    // Centro inicial: Santa Cruz de la Sierra, Bolivia.
    const centro = [-17.7833, -63.1821];
    const mapa = L.map('mapa-ubicacion').setView(centro, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    let marcador = null;
    const latitud = document.getElementById('latitud');
    const longitud = document.getElementById('longitud');
    const texto = document.getElementById('coordenadas-texto');

    function seleccionarPunto(lat, lng) {
        latitud.value = Number(lat).toFixed(6);
        longitud.value = Number(lng).toFixed(6);
        texto.textContent = 'Punto seleccionado: ' + latitud.value + ', ' + longitud.value;
        if (marcador) marcador.setLatLng([lat, lng]);
        else marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
        marcador.bindPopup('Ubicación del problema').openPopup();
        marcador.off('dragend');
        marcador.on('dragend', function (evento) {
            const punto = evento.target.getLatLng();
            seleccionarPunto(punto.lat, punto.lng);
        });
    }

    mapa.on('click', function (evento) {
        seleccionarPunto(evento.latlng.lat, evento.latlng.lng);
    });

    document.getElementById('usar-mi-ubicacion').addEventListener('click', function () {
        if (!navigator.geolocation) {
            texto.textContent = 'Tu navegador no permite obtener la ubicación. Marca el punto en el mapa.';
            return;
        }
        texto.textContent = 'Obteniendo tu ubicación…';
        navigator.geolocation.getCurrentPosition(function (posicion) {
            const lat = posicion.coords.latitude;
            const lng = posicion.coords.longitude;
            mapa.setView([lat, lng], 17);
            seleccionarPunto(lat, lng);
        }, function () {
            texto.textContent = 'No se pudo obtener la ubicación. Permite el acceso o marca el punto en el mapa.';
        }, { enableHighAccuracy: true, timeout: 10000 });
    });

    setTimeout(function () { mapa.invalidateSize(); }, 250);
})();

</script>


</body>
</html>