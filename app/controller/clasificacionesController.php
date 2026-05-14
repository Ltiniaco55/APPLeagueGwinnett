<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/clasificacionesModel.php';

class ClasificacionesController
{
    private function responder(int $codigoHttp, array $contenido): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function seleccionarPorLiga(int $idLiga): void
    {
        try {
            if ($idLiga <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de liga no válido'
                ]);
            }

            $modelo = new ClasificacionesModel();

            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada'
                ]);
            }

            $modelo->asegurarClasificacionLiga($idLiga);

            $datos = $modelo->getByLiga($idLiga);

            $this->responder(200, [
                'success' => true,
                'data' => $datos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }

    public function regenerar(int $idLiga): void
    {
        try {
            if ($idLiga <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de liga no válido'
                ]);
            }

            $modelo = new ClasificacionesModel();

            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada'
                ]);
            }

            $modelo->regenerarLiga($idLiga);

            $this->responder(200, [
                'success' => true,
                'message' => 'Clasificación regenerada correctamente'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }
}
