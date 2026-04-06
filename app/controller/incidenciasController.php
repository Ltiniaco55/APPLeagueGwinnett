<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Autenticacion.php';

class IncidenciasController
{
    private function responder(int $codigoHttp, array $contenido): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function abrirIncidencia(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF]);
            $usuario = Autenticacion::usuario();

            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $nombre_equipo = trim((string)($entrada['nombre_equipo'] ?? 'Unknown Team'));
            $descripcion = trim((string)($entrada['descripcion'] ?? ''));

            if (!$id_equipo || !$descripcion) {
                $this->responder(400, ['success' => false, 'message' => 'Falta equipo o descripción']);
            }

            // Validar permiso
            Autenticacion::requerirStaffDeEquipo($id_equipo);

            $logData = [
                'id_usuario' => $usuario['id_usuario'],
                'nombre' => trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')),
                'email' => $usuario['email'],
                'id_equipo' => $id_equipo,
                'nombre_equipo' => $nombre_equipo,
                'descripcion' => $descripcion,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $logPath = __DIR__ . '/../../logs';
            if (!is_dir($logPath)) {
                mkdir($logPath, 0777, true);
            }
            
            $file = $logPath . '/email_dev.log';
            file_put_contents($file, json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

            $this->responder(201, [
                'success' => true,
                'message' => 'Incidencia abierta y registrada correctamente'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
