<?php
   //Datos para la conexion a la BD
   $host = "localhost";
   $user = "root";
   $pass = "";
   $db = "gestor_cafe_recolectado";

   //Ago la conexion a la BD
   $con = new mysqli($host,$user,$pass,$db);

   //Verificamos la conexion
   if($con->connect_error){
      // die ("Error en la conexion");
      echo "Error en la conexion!!";
   }
   // else{
   //  echo "Todo bien parce..";
   // }
?>