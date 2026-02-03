<?php

$nombre_liga = $_GET['nombre_liga'] ?? '';
$temporada   = $_GET['temporada'] ?? '';
$categoria   = $_GET['categoria'] ?? '';
$descripcion = $_GET['descripcion'] ?? '';

$conn = new mysqli("localhost", "root", "", "gwinnett_league");

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$sql = "INSERT INTO ligas (nombre_liga, temporada, categoria, descripcion)
        VALUES ('$nombre_liga', '$temporada', '$categoria', '$descripcion')";

if ($conn->query($sql) === TRUE) {
    echo "Liga creada correctamente";
} else {
    echo "Error al insertar la liga: " . $conn->error;
}

$conn->close();
?>
