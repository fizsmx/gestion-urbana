<?php

    $servidor = "localhost";
    $usuario = "root";
    $clave = "";
    $basededatos = "ejemplo";

    $enlace = mysqli_connect($servidor,$usuario,$clave,$basededatos);

?>



<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Formulario</title>
    </head>
    <body>


        <form action="#" name="ejemplo" method="post">

        <input type="text" name="nombre" placeholder="nombre">
        <input type="email" name="correo" placeholder="correo">
        <input type="text" name="telefono" placeholder="telefono">

        <input type="submit" name="registro">
        <input type="reset">

    </form>

</body>


<?php

    if(isset($_POST['registro'])){

        $nombre = $_POST['nombre'];
        $correo = $_POST['correo'];
        $telefono = $_POST['telefono'];


        $insertarDatos = "INSERT INTO datos VALUES('$nombre','$correo','$telefono','')";

    
        $ejecutarInsertar = mysqli_query($enlace,$insertarDatos);


    }


?>
