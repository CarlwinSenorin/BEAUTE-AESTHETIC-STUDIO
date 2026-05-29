<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();
?>
<?php include '../includes/admin-header.php'; ?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<style>
    .admin-calendar-card {
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-top: 20px;
    }
    #calendar { max-width: 100%; }
    .fc-event { cursor: pointer; padding: 2px 5px; font-size: 0.82rem; }
    .status-pending { background-color: #ffc107 !important; color: #000 !important; border-color: #ffc107 !important; }
    .status-reserved { background-color: #fd7e14 !important; border-color: #fd7e14 !important; }
    .status-confirmed { background-color: #28a745 !important; border-color: #28a745 !important; }
    .status-completed { background-color: #6c757d !important; border-color: #6c757d !important; }
    .status-in_progress { background-color: #17a2b8 !important; border-color: #17a2b8 !important; }

    /* Status Legend */
    .calendar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 12px 16px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: #555;
    }
    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background-color: #fefefe;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 650px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        position: relative;
        max-height: 80vh;
        overflow-y: auto;
    }
    .close-modal {
        position: absolute;
        right: 20px; top: 15px;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }
    .close-modal:hover { color: #333; }
    .modal-header h2 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .admin-appointment-list { list-style: none; padding: 0; }
    .admin-appointment-item {
        padding: 15px;
        border: 1px solid #eee;
        border-radius: 10px;
        margin-bottom: 10px;
        background: #fafafa;
    }
    .admin-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .admin-item-time { font-weight: 700; color: #d4a574; }
    .admin-item-client { font-weight: 600; font-size: 1.05rem; margin-bottom: 4px; }
    .admin-item-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 1rem;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 8px;
    }
    .admin-item-details i { color: #aaa; width: 16px; margin-right: 4px; }
    .admin-item-actions {
        display: flex;
        gap: 8px;
    }
</style>

<div class="admin-header">
    <h1><i class="fas fa-calendar-alt"></i> Appointment Calendar</h1>
    <div class="admin-breadcrumb">
        <a href="index.php">Dashboard</a> / <span>Calendar</span>
    </div>
</div>

<div class="admin-calendar-card">
    <!-- Status Legend -->
    <div class="calendar-legend">
        <div class="legend-item"><div class="legend-dot" style="background:#ffc107;"></div> Pending</div>
        <div class="legend-item"><div class="legend-dot" style="background:#fd7e14;"></div> Reserved</div>
        <div class="legend-item"><div class="legend-dot" style="background:#28a745;"></div> Confirmed</div>
        <div class="legend-item"><div class="legend-dot" style="background:#17a2b8;"></div> In Progress</div>
        <div class="legend-item"><div class="legend-dot" style="background:#6c757d;"></div> Completed</div>
    </div>
    <div id='calendar'></div>
</div>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="modal-header">
            <h2 id="modalDate">Appointments</h2>
        </div>
        <div id="appointmentListContainer">
            <div class="admin-appointment-list" id="appointmentList">
                <!-- Loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var modal = document.getElementById("appointmentModal");
        var span = document.getElementsByClassName("close-modal")[0];
        var appointmentList = document.getElementById("appointmentList");
        var modalDate = document.getElementById("modalDate");

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: '../api/get-booked-appointments.php',
            dateClick: function(info) {
                showAppointmentsForDate(info.dateStr);
            },
            eventClick: function(info) {
                showAppointmentsForDate(info.event.startStr.split('T')[0]);
            },
            eventDidMount: function(info) {
                // Tooltip with more info
                const props = info.event.extendedProps;
                if (props.service) {
                    info.el.title = props.client_name + '\n' + props.service + '\nStaff: ' + props.staff;
                }
            }
        });
        calendar.render();

        function showAppointmentsForDate(dateStr) {
            // Use next day for end range to include same-day appointments
            const nextDay = new Date(dateStr);
            nextDay.setDate(nextDay.getDate() + 1);
            const endStr = nextDay.toISOString().split('T')[0];

            modalDate.innerText = "Schedule for " + new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { dateStyle: 'long' });
            appointmentList.innerHTML = '<p style="text-align:center; color:#999;">Loading...</p>';
            modal.style.display = "flex";

            fetch(`../api/get-booked-appointments.php?start=${dateStr}&end=${endStr}`)
                .then(response => response.json())
                .then(data => {
                    appointmentList.innerHTML = '';
                    if (data.length === 0) {
                        appointmentList.innerHTML = '<p class="empty-state" style="text-align:center; color:#999; padding:2rem;">No appointments scheduled for this date.</p>';
                    } else {
                        data.sort((a, b) => a.start.localeCompare(b.start));
                        
                        data.forEach(app => {
                            const time = new Date(app.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            const endTime = app.end ? new Date(app.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                            const props = app.extendedProps;
                            const div = document.createElement('div');
                            div.className = 'admin-appointment-item';
                            
                            const statusLabel = props.status.charAt(0).toUpperCase() + props.status.slice(1).replace('_', ' ');
                            const statusBadge = `<span class="status-badge status-${props.status}">${statusLabel}</span>`;
                            const paxInfo = props.pax > 1 ? ` <span style="background:rgba(212,165,116,0.15); color:#8b6914; padding:2px 8px; border-radius:12px; font-size:0.78rem; font-weight:600;">${props.pax} pax</span>` : '';
                            
                            div.innerHTML = `
                                <div class="admin-item-header">
                                    <span class="admin-item-time">${time}${endTime ? ' – ' + endTime : ''}</span>
                                    ${statusBadge}${paxInfo}
                                </div>
                                <div class="admin-item-client">${props.client_name || 'Unknown'}</div>
                                <div class="admin-item-details">
                                    <span><i class="fas fa-spa"></i> ${props.service || 'N/A'}</span>
                                    <span><i class="fas fa-user-tie"></i> ${props.staff || 'Unassigned'}</span>
                                    <span><i class="fas fa-phone"></i> ${props.phone || ''}</span>
                                    <span><i class="fas fa-money-bill"></i> ₱${parseFloat(props.price || 0).toLocaleString(undefined, {minimumFractionDigits:2})} (${props.payment_method || 'N/A'})</span>
                                </div>
                                <div class="admin-item-actions">
                                    <a href="appointment-details.php?id=${app.id}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View Details</a>
                                    <a href="live-monitor.php" class="btn btn-sm btn-outline"><i class="fas fa-heartbeat"></i> Monitor</a>
                                </div>
                            `;
                            appointmentList.appendChild(div);
                        });
                    }
                });
        }

        span.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) {
            if (event.target == modal) modal.style.display = "none";
        }
    });
</script>

<?php include '../includes/admin-footer.php'; ?>
