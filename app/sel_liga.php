<?php

$nombre_liga = $_GET['nombre_liga'] ?? '';
$temporada   = $_GET['temporada'] ?? '';
$categoria   = $_GET['categoria'] ?? '';

$conn = new mysqli("localhost", "root", "", "gwinnett_league");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM ligas
        WHERE UCASE(nombre_liga) LIKE '%$nombre_liga%'
        AND UCASE(temporada) LIKE '%$temporada%'
        AND UCASE(categoria) LIKE '%$categoria%'";

$result = $conn->query($sql);

$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);

$conn->close();
?>
