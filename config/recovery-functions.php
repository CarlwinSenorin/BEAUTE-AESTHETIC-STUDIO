<?php
/**
 * Recovery Bin Functions
 * 
 * Soft delete functionality for appointments
 */

/**
 * Soft delete an appointment
 */
function softDeleteAppointment($appointment_id, $reason = null) {
    $conn = getDBConnection();
    
    // Verify appointment exists and user has permission
    $stmt = $conn->prepare("SELECT user_id FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        return ['success' => false, 'message' => 'Appointment not found'];
    }
    
    // Check permission (user can delete own appointments, admin can delete any)
    if (!isAdmin() && $appointment['user_id'] != $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $stmt = $conn->prepare("UPDATE appointments 
                            SET deleted_at = NOW(),
                                deleted_by = ?,
                                deletion_reason = ?
                            WHERE id = ?");
    
    $result = $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $reason,
        $appointment_id
    ]);
    
    return [
        'success' => $result,
        'message' => $result ? 'Appointment moved to recovery bin' : 'Failed to delete appointment'
    ];
}

/**
 * Restore a soft-deleted appointment
 */
function restoreAppointment($appointment_id) {
    $conn = getDBConnection();
    
    // Get appointment details
    $stmt = $conn->prepare("SELECT appointment_date, appointment_time, end_time, staff_id, user_id 
                            FROM appointments WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$appointment_id]);
    $apt = $stmt->fetch();
    
    if (!$apt) {
        return ['success' => false, 'message' => 'Deleted appointment not found'];
    }
    
    // Check permission
    if (!isAdmin() && $apt['user_id'] != $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    // Check if time slot is still available (prevent conflicts)
    if ($apt['staff_id']) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                                WHERE appointment_date = ?
                                AND staff_id = ?
                                AND deleted_at IS NULL
                                AND status NOT IN ('cancelled', 'no_show')
                                AND NOT (end_time <= ? OR appointment_time >= ?)");
        $stmt->execute([
            $apt['appointment_date'], 
            $apt['staff_id'], 
            $apt['appointment_time'], 
            $apt['end_time']
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Time slot no longer available. Staff has another booking.'];
        }
    }
    
    // Restore appointment
    $stmt = $conn->prepare("UPDATE appointments 
                            SET deleted_at = NULL,
                                deleted_by = NULL,
                                deletion_reason = NULL
                            WHERE id = ?");
    $result = $stmt->execute([$appointment_id]);
    
    return [
        'success' => $result,
        'message' => $result ? 'Appointment restored successfully' : 'Failed to restore appointment'
    ];
}

/**
 * Get deleted appointments (recovery bin)
 */
function getDeletedAppointments($user_id = null, $is_admin = false) {
    $conn = getDBConnection();
    
    $sql = "SELECT a.*, 
            u.first_name, u.last_name, u.email,
            su.first_name as staff_name,
            deleter.first_name as deleted_by_name
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN staff s ON a.staff_id = s.id
            LEFT JOIN users su ON s.user_id = su.id
            LEFT JOIN users deleter ON a.deleted_by = deleter.id
            WHERE a.deleted_at IS NOT NULL";
    
    $params = [];
    
    if (!$is_admin && $user_id) {
        $sql .= " AND a.user_id = ?";
        $params[] = $user_id;
    }
    
    $sql .= " ORDER BY a.deleted_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Permanently delete an appointment
 */
function permanentlyDeleteAppointment($appointment_id) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Only admins can permanently delete appointments'];
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND deleted_at IS NOT NULL");
    $result = $stmt->execute([$appointment_id]);
    
    return [
        'success' => $result,
        'message' => $result ? 'Appointment permanently deleted' : 'Failed to delete appointment'
    ];
}

/**
 * Auto-purge appointments deleted more than 30 days ago
 */
function purgeOldDeletedAppointments() {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM appointments 
                            WHERE deleted_at IS NOT NULL 
                            AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    return $stmt->rowCount();
}

/**
 * Get count of items in recovery bin
 */
function getRecoveryBinCount($user_id = null) {
    $conn = getDBConnection();
    
    if ($user_id && !isAdmin()) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                                WHERE deleted_at IS NOT NULL AND user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $conn->query("SELECT COUNT(*) FROM appointments WHERE deleted_at IS NOT NULL");
    }
    
    return $stmt->fetchColumn();
}
