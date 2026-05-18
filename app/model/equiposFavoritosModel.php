<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/database.php';

class EquiposFavoritosModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener favoritos del usuario
     */
    public function seleccionarPorUsuario(int $idUsuario): array
    {
        $sql = "
            SELECT
                uef.id_usuario,
                uef.id_equipo,
                e.club,
                e.categoria
            FROM usuario_equipo_favorito uef
            INNER JOIN equipos e
                ON e.id_equipo = uef.id_equipo
            WHERE uef.id_usuario = :id_usuario
            ORDER BY e.club ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Verifica si ya existe favorito
     */
    public function existe(int $idUsuario, int $idEquipo): bool
    {
        $sql = "
            SELECT 1
            FROM usuario_equipo_favorito
            WHERE id_usuario = :id_usuario
              AND id_equipo = :id_equipo
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_equipo' => $idEquipo
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Añadir favorito
     */
    public function insertar(int $idUsuario, int $idEquipo): bool
    {
        $sql = "
            INSERT INTO usuario_equipo_favorito (
                id_usuario,
                id_equipo
            ) VALUES (
                :id_usuario,
                :id_equipo
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_equipo' => $idEquipo
        ]);
    }

    /**
     * Eliminar favorito
     */
    public function eliminar(int $idUsuario, int $idEquipo): bool
    {
        $sql = "
            DELETE FROM usuario_equipo_favorito
            WHERE id_usuario = :id_usuario
              AND id_equipo = :id_equipo
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_equipo' => $idEquipo
        ]);
    }

    /**
     * Obtener ligas asociadas a un equipo
     */
    public function seleccionarLigasPorEquipo(int $idEquipo): array
    {
        $sql = "
            SELECT
                l.id_liga,
                l.nombre_liga,
                l.categoria,
                l.formato_liga,
                l.temporada
            FROM equipo_liga el
            INNER JOIN ligas l
                ON l.id_liga = el.id_liga
            WHERE el.id_equipo = :id_equipo
            ORDER BY l.nombre_liga ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_equipo', $idEquipo, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtener favoritos + ligas agrupadas
     */
    public function seleccionarFavoritosConLigas(int $idUsuario): array
    {
        $favoritos = $this->seleccionarPorUsuario($idUsuario);

        foreach ($favoritos as &$favorito) {
            $favorito['ligas'] = $this->seleccionarLigasPorEquipo(
                (int)$favorito['id_equipo']
            );
        }

        return $favoritos;
    }

    /**
     * Próximos partidos de todos los favoritos
     */
    public function seleccionarProximosPartidosFavoritos(
        int $idUsuario,
        int $limite = 16
    ): array {
        $sql = "
            SELECT DISTINCT
                p.id_partido,
                p.id_liga,
                p.tipo_ronda,
                p.fecha,
                p.lugar,
                p.estado,
                p.goles_local,
                p.goles_visitante,
                el.club AS club_local,
                ev.club AS club_visitante,
                l.nombre_liga,
                l.formato_liga,
                l.categoria
            FROM usuario_equipo_favorito uef
            INNER JOIN partidos p
                ON (
                    p.id_equipo_local = uef.id_equipo
                    OR p.id_equipo_visitante = uef.id_equipo
                )
            INNER JOIN equipos el
                ON el.id_equipo = p.id_equipo_local
            INNER JOIN equipos ev
                ON ev.id_equipo = p.id_equipo_visitante
            INNER JOIN ligas l
                ON l.id_liga = p.id_liga
            WHERE uef.id_usuario = :id_usuario
              AND p.estado = 'programado'
              AND p.fecha >= NOW()
            ORDER BY p.fecha ASC
            LIMIT :limite
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
