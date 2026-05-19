<?php
declare(strict_types=1);

class AppointmentRepository extends AbstractRepository
{
    protected string $table = 'appointments';
    protected string $primaryKey = 'appointment_id';
    protected array $allowedSortColumns = ['appointment_id', 'appointment_datetime', 'status', 'total_cost'];

    public function getByDate(string $date): array
    {
        $sql = "SELECT a.*, c.last_name, c.first_name, car.make, car.model, 
                       s.service_name, s.price
                FROM appointments a
                JOIN clients c ON a.client_id = c.client_id
                JOIN cars car ON a.car_id = car.car_id
                JOIN services s ON a.service_id = s.service_id
                WHERE DATE(a.appointment_datetime) = ?
                ORDER BY a.appointment_datetime ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    /**
     * Создание записи с транзакцией и фиксацией запчастей (если статус завершено)
     */
    public function createAppointment(int $clientId, int $carId, int $serviceId, string $datetime, string $status = 'запланировано'): int
    {
        $this->pdo->beginTransaction();
        try {
            // Вставка записи (триггер БД автоматически проверит наличие запчастей)
            $stmt = $this->pdo->prepare("
                INSERT INTO appointments (client_id, car_id, service_id, appointment_datetime, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$clientId, $carId, $serviceId, $datetime, $status]);
            $appointmentId = (int)$this->pdo->lastInsertId();

            // Если запись сразу помечена как завершённая, фиксируем расход запчастей
            if ($status === 'завершено') {
                $stmtParts = $this->pdo->prepare("
                    INSERT INTO appointment_parts (appointment_id, part_id, quantity_used)
                    SELECT ?, part_id, required_quantity FROM service_parts WHERE service_id = ?
                ");
                $stmtParts->execute([$appointmentId, $serviceId]);
            }

            $this->pdo->commit();
            return $appointmentId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new RepositoryException("Ошибка создания записи: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatus(int $id, string $newStatus): bool
    {
        $allowed = ['запланировано', 'в работе', 'завершено', 'отменено'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new RepositoryException("Недопустимый статус записи: $newStatus");
        }
        return $this->update($id, ['status' => $newStatus]);
    }
}
