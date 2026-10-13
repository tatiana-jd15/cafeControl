<?php
  $documento=$_POST['cedula'];
  $nuevaClave=$_POST['nueva_clave'];
    $confirmar_clave=$_POST ['nueva'];

    if($nuevaClave==$confirmarclave){
        $sql="UPDATE usuarios SET contrasena=$nuevaClave WHERE documento=$documento";
    };

 
?>