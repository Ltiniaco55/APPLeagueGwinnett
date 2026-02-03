<?php

$id = $_GET['id_liga'] ?? '';

$conn = new mysqli("localhost", "root", "", "gwinnett_league");
if ($conn->connect_error) {
    die("Fallo la conexión: " . $conn->connect_error);
}

$sql = "DELETE FROM ligas WHERE id_liga = '$id'";

if ($conn->query($sql) === TRUE) {
    echo "Borrado exitoso";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
