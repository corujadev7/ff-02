<?php
// includes/Logger.php
class Logger {
    private $db;
    private $ip;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ip = $this->getClientIP();
    }
    
    private function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    // Log de consulta de placa
    public function logPlateSearch($placa, $resultado = 'found', $dadosVeiculo = null, $tempoResposta = null) {
        try {
            $sql = "INSERT INTO plate_search_logs (placa, ip, user_agent, resultado, dados_veiculo, tempo_resposta) 
                    VALUES (:placa, :ip, :user_agent, :resultado, :dados_veiculo, :tempo_resposta)";
            
            $this->db->query($sql, [
                ':placa' => $placa,
                ':ip' => $this->ip,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':resultado' => $resultado,
                ':dados_veiculo' => $dadosVeiculo ? json_encode($dadosVeiculo) : null,
                ':tempo_resposta' => $tempoResposta
            ]);
            
            // Atualizar estatísticas do IP
            $this->updateIPStats('plate_search');
            
        } catch (Exception $e) {
            error_log("Erro ao logar consulta de placa: " . $e->getMessage());
        }
    }
    
    // Log de acesso à página
    public function logPageAccess($pagina, $tempoCarregamento = null) {
        try {
            $sql = "INSERT INTO page_access_logs (ip, pagina, metodo, user_agent, referer, query_string, tempo_carregamento) 
                    VALUES (:ip, :pagina, :metodo, :user_agent, :referer, :query_string, :tempo_carregamento)";
            
            $this->db->query($sql, [
                ':ip' => $this->ip,
                ':pagina' => $pagina,
                ':metodo' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':referer' => $_SERVER['HTTP_REFERER'] ?? null,
                ':query_string' => $_SERVER['QUERY_STRING'] ?? null,
                ':tempo_carregamento' => $tempoCarregamento
            ]);
            
            // Atualizar estatísticas do IP
            $this->updateIPStats('page_view');
            
        } catch (Exception $e) {
            error_log("Erro ao logar acesso à página: " . $e->getMessage());
        }
    }
    
    // Atualizar estatísticas do IP
    private function updateIPStats($tipo) {
        try {
            // Verificar se IP já existe
            $check = $this->db->query("SELECT id FROM ip_stats WHERE ip = :ip", [':ip' => $this->ip])->fetch();
            
            if ($check) {
                if ($tipo == 'plate_search') {
                    $sql = "UPDATE ip_stats SET total_consultas_placa = total_consultas_placa + 1, ultima_visita = NOW() WHERE ip = :ip";
                } else {
                    $sql = "UPDATE ip_stats SET total_visitas = total_visitas + 1, ultima_visita = NOW() WHERE ip = :ip";
                }
                $this->db->query($sql, [':ip' => $this->ip]);
            } else {
                $sql = "INSERT INTO ip_stats (ip, total_visitas, total_consultas_placa, primeira_visita, ultima_visita) 
                        VALUES (:ip, :total_visitas, :total_consultas, NOW(), NOW())";
                $this->db->query($sql, [
                    ':ip' => $this->ip,
                    ':total_visitas' => $tipo == 'page_view' ? 1 : 0,
                    ':total_consultas' => $tipo == 'plate_search' ? 1 : 0
                ]);
            }
        } catch (Exception $e) {
            error_log("Erro ao atualizar estatísticas do IP: " . $e->getMessage());
        }
    }
    
    // Obter logs de consultas de placas
    public function getPlateSearchLogs($limit = 100, $offset = 0, $filtros = []) {
        $sql = "SELECT * FROM plate_search_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['placa'])) {
            $sql .= " AND placa LIKE :placa";
            $params[':placa'] = '%' . $filtros['placa'] . '%';
        }
        
        if (!empty($filtros['ip'])) {
            $sql .= " AND ip LIKE :ip";
            $params[':ip'] = '%' . $filtros['ip'] . '%';
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND created_at >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND created_at <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT $offset, $limit";
        
        $logs = $this->db->query($sql, $params)->fetchAll();
        
        // Total de registros
        $countSql = "SELECT COUNT(*) as total FROM plate_search_logs WHERE 1=1";
        // Aplicar mesmos filtros para contagem...
        $total = $this->db->query($countSql, $params)->fetch();
        
        return [
            'data' => $logs,
            'total' => $total['total'] ?? 0
        ];
    }
    
    // Obter logs de acesso às páginas
    public function getPageAccessLogs($limit = 100, $offset = 0, $filtros = []) {
        $sql = "SELECT * FROM page_access_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['ip'])) {
            $sql .= " AND ip LIKE :ip";
            $params[':ip'] = '%' . $filtros['ip'] . '%';
        }
        
        if (!empty($filtros['pagina'])) {
            $sql .= " AND pagina LIKE :pagina";
            $params[':pagina'] = '%' . $filtros['pagina'] . '%';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT $offset, $limit";
        
        return $this->db->query($sql, $params)->fetchAll();
    }
    
    // Obter estatísticas gerais
    public function getStats() {
        $stats = [];
        
        // Total de consultas hoje
        $result = $this->db->query("SELECT COUNT(*) as total FROM plate_search_logs WHERE DATE(created_at) = CURDATE()")->fetch();
        $stats['consultas_hoje'] = $result['total'];
        
        // Total de consultas este mês
        $result = $this->db->query("SELECT COUNT(*) as total FROM plate_search_logs WHERE MONTH(created_at) = MONTH(NOW())")->fetch();
        $stats['consultas_mes'] = $result['total'];
        
        // Total de IPs únicos
        $result = $this->db->query("SELECT COUNT(DISTINCT ip) as total FROM plate_search_logs")->fetch();
        $stats['ips_unicos'] = $result['total'];
        
        // Placas mais consultadas
        $stats['placas_top'] = $this->db->query("SELECT placa, COUNT(*) as total FROM plate_search_logs GROUP BY placa ORDER BY total DESC LIMIT 10")->fetchAll();
        
        // IPs mais ativos
        $stats['ips_top'] = $this->db->query("SELECT ip, COUNT(*) as total FROM plate_search_logs GROUP BY ip ORDER BY total DESC LIMIT 10")->fetchAll();
        
        // Horários de pico
        $stats['horarios_pico'] = $this->db->query("SELECT HOUR(created_at) as hora, COUNT(*) as total FROM plate_search_logs GROUP BY HOUR(created_at) ORDER BY total DESC LIMIT 5")->fetchAll();
        
        return $stats;
    }
    
    // Bloquear IP
    public function blockIP($ip, $motivo, $minutos = 60) {
        $expires = date('Y-m-d H:i:s', time() + ($minutos * 60));
        $sql = "INSERT INTO ip_blocks (ip, motivo, expires_at) VALUES (:ip, :motivo, :expires_at) 
                ON DUPLICATE KEY UPDATE motivo = :motivo, expires_at = :expires_at";
        
        return $this->db->query($sql, [
            ':ip' => $ip,
            ':motivo' => $motivo,
            ':expires_at' => $expires
        ]);
    }
    
    // Verificar se IP está bloqueado
    public function isIPBlocked($ip = null) {
        $ip = $ip ?? $this->ip;
        $sql = "SELECT * FROM ip_blocks WHERE ip = :ip AND expires_at > NOW()";
        $result = $this->db->query($sql, [':ip' => $ip])->fetch();
        return !empty($result);
    }
}
?>