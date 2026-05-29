<?php
require_once 'config/functions.php';
requireLogin();

$current_user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Calendar - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        .calendar-section {
            padding: 40px 0;
            background-color: #f8f9fa;
            min-height: 80vh;
        }
        .calendar-container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .calendar-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 700;
            color: #333;
        }
        .fc-button-primary {
            background-color: #d4a574 !important;
            border-color: #d4a574 !important;
        }
        .fc-button-primary:hover {
            background-color: #b88a5d !important;
            border-color: #b88a5d !important;
        }
        .fc-daygrid-event {
            cursor: pointer;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }
        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .modal-header h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .appointment-list {
            list-style: none;
            padding: 0;
        }
        .appointment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .appointment-item:last-child {
            border-bottom: none;
        }
        .appointment-time {
            font-weight: 600;
            color: #d4a574;
            min-width: 100px;
        }
        .appointment-status-text {
            color: #666;
            font-style: italic;
        }
        .no-appointments {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        @media (max-width: 768px) {
            .calendar-container {
                padding: 15px;
            }
            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="calendar-section">
        <div class="container">
            <div class="calendar-header">
                <div>
                    <h1>Appointment Calendar</h1>
                    <p>View availability and plan your next visit</p>
                </div>
                <a href="booking.php" class="btn btn-primary">Book Now</a>
            </div>

            <div class="calendar-container">
                <div id='calendar'></div>
            </div>
        </div>
    </section>

    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h2 id="modalDate">Appointments</h2>
            </div>
            <div id="appointmentListContainer">
                <ul class="appointment-list" id="appointmentList">
                    <!-- Loaded via JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
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
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: 'api/get-booked-appointments.php',
                dateClick: function(info) {
                    showAppointmentsForDate(info.dateStr);
                },
                eventClick: function(info) {
                    showAppointmentsForDate(info.event.startStr.split('T')[0]);
                }
            });
            calendar.render();

            function showAppointmentsForDate(dateStr) {
                modalDate.innerText = "Booked Slots for " + new Date(dateStr).toLocaleDateString(undefined, { dateStyle: 'long' });
                appointmentList.innerHTML = '<li>Loading...</li>';
                modal.style.display = "flex";

                fetch(`api/get-booked-appointments.php?start=${dateStr}&end=${dateStr}`)
                    .then(response => response.json())
                    .then(data => {
                        appointmentList.innerHTML = '';
                        if (data.length === 0) {
                            appointmentList.innerHTML = '<li class="no-appointments">No bookings for this date. All slots are available!</li>';
                        } else {
                            // Sort by time
                            data.sort((a, b) => a.start.localeCompare(b.start));
                            
                            data.forEach(app => {
                                const time = new Date(app.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                const li = document.createElement('li');
                                li.className = 'appointment-item';
                                li.innerHTML = `
                                    <span class="appointment-time">${time}</span>
                                    <span class="appointment-status-text">Reserved</span>
                                `;
                                appointmentList.appendChild(li);
                            });
                        }
                    });
            }

            span.onclick = function() {
                modal.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });
    </script>
</body>
</html>
