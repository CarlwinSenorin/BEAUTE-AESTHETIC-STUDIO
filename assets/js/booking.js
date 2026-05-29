// Refined Booking System JavaScript
let paxCount = 1;
let currentPersonIndex = 1;
let selectedServices = []; // Current person's selected services
let selectedPackage = null; // Current person's selected package
let serviceAssignments = {}; // Current person's assignments
let allPersonSelections = []; // [{personIndex, services, package, assignments}]
let globalDate = null; // Global date for the entire booking

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    initializePaxSelection();
    initializeGlobalDatePicker();
    initializeServiceSelection();
    initializePackageSelection();

    // Set default pax if hidden input exists
    const paxInput = document.getElementById('paxInput');
    if (paxInput) paxCount = parseInt(paxInput.value || 1);
});

function initializeGlobalDatePicker() {
    const dateInput = document.getElementById('globalDateInput');
    const container = document.getElementById('globalDatePickerInline');

    if (!container) return;

    flatpickr(container, {
        inline: true,
        minDate: 'today',
        dateFormat: 'Y-m-d',
        disable: [function (date) { return date.getDay() === 0; }], // Sunday disable
        onChange: function (selectedDates, dateStr) {
            globalDate = dateStr;
            if (dateInput) dateInput.value = dateStr;
        }
    });
}

function initializePaxSelection() {
    const paxRadios = document.querySelectorAll('input[name="pax_radio"]');
    paxRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            paxCount = parseInt(this.value);
            const paxInput = document.getElementById('paxInput');
            if (paxInput) paxInput.value = paxCount;
        });
    });
}

function initializeServiceSelection() {
    const checkboxes = document.querySelectorAll('input[name="services[]"]');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const svcId = this.value;
            if (this.checked) {
                // If package was selected, deselect it
                if (selectedPackage) {
                    deselectPackage();
                }

                // One service per category rule
                const myCategory = this.dataset.category;
                if (myCategory) {
                    checkboxes.forEach(other => {
                        if (other !== this && other.dataset.category === myCategory && other.checked) {
                            other.checked = false;
                            removeService(other.value);
                        }
                    });
                }

                addService(svcId, this);
            } else {
                removeService(svcId);
            }
            updateServiceSummary();
        });
    });
}

function addService(id, el) {
    const service = {
        id: id,
        instanceId: Date.now() + Math.random().toString(36).substr(2, 9), // Unique instance ID
        duration: parseInt(el.dataset.duration),
        price: parseFloat(el.dataset.price),
        name: el.dataset.name || el.closest('.service-checkbox').querySelector('.service-name').textContent,
        category: el.dataset.category
    };
    selectedServices.push(service);
}

function removeService(id) {
    // Remove all instances of this service
    selectedServices = selectedServices.filter(s => s.id !== id);
    // Also remove from assignments by instanceId
    Object.keys(serviceAssignments).forEach(key => {
        if (selectedServices.every(s => s.instanceId !== key)) {
            delete serviceAssignments[key];
        }
    });

    const el = document.querySelector(`input[name="services[]"][value="${id}"]`);
}


function initializePackageSelection() {
    const packageCards = document.querySelectorAll('.package-mini-card');
    packageCards.forEach(card => {
        card.addEventListener('click', function () {
            const isSelected = this.classList.contains('selected');
            deselectPackage();

            if (!isSelected) {
                this.classList.add('selected');
                const pkgServices = JSON.parse(this.dataset.services || '[]');
                const pkgPrice = parseFloat(this.dataset.price) || 0;
                const pkgPax = parseInt(this.dataset.pax) || 1;

                selectedPackage = {
                    id: this.dataset.packageId,
                    name: this.querySelector('h4').textContent,
                    duration: parseInt(this.dataset.duration) || 60,
                    price: pkgPrice,
                    pax: pkgPax
                };

                // Auto-set pax from package
                paxCount = pkgPax;
                const paxInput = document.getElementById('paxInput');
                if (paxInput) paxInput.value = paxCount;

                // Populate selectedServices from the package's component services
                selectedServices = pkgServices.map(svc => ({
                    id: String(svc.id),
                    instanceId: Date.now() + Math.random().toString(36).substr(2, 9),
                    duration: parseInt(svc.duration) || 30,
                    price: parseFloat(svc.price) || 0,
                    name: svc.name,
                    category: (svc.category || '').toLowerCase()
                }));

                // Disable individual service checkboxes
                document.querySelectorAll('input[name="services[]"]').forEach(cb => {
                    cb.checked = false;
                    cb.disabled = true;
                });
                serviceAssignments = {};
            }
            updateServiceSummary();
        });
    });
}

function deselectPackage() {
    selectedPackage = null;
    document.querySelectorAll('.package-mini-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('input[name="services[]"]').forEach(cb => {
        cb.disabled = false;
        // cb.closest('.service-checkbox').style.opacity = '1';
    });
}

function updateServiceSummary() {
    const summaryDiv = document.getElementById('selectedServicesSummary');
    const listDiv = document.getElementById('selectedServicesList');
    const totalDurSpan = document.getElementById('totalDuration');
    const totalPriceSpan = document.getElementById('totalPrice');

    if (selectedServices.length === 0 && !selectedPackage) {
        summaryDiv.style.display = 'none';
        return;
    }

    summaryDiv.style.display = 'block';
    let html = '';
    let totalDur = 0;
    let totalPrice = 0;

    if (selectedPackage) {
        html = `<div class="summary-item"><span>${selectedPackage.name} (Package)</span><span>${selectedPackage.price ? formatPrice(selectedPackage.price) : ''}</span></div>`;
        totalDur = selectedPackage.duration;
        totalPrice = selectedPackage.price || 0;
    } else {
        html = selectedServices.map(s => `
            <div class="summary-item">
                <span>${s.name} ${selectedServices.filter(item => item.id === s.id).length > 1 ? `(Instance ${selectedServices.filter(item => item.id === s.id).indexOf(s) + 1})` : ''}</span>
                <span>${formatPrice(s.price)}</span>
            </div>
        `).join('');
        totalDur = selectedServices.reduce((sum, s) => sum + s.duration, 0);
        totalPrice = selectedServices.reduce((sum, s) => sum + s.price, 0);
    }

    listDiv.innerHTML = html;
    if (totalDurSpan) totalDurSpan.textContent = totalDur;
    if (totalPriceSpan) totalPriceSpan.textContent = formatPrice(totalPrice);
}

function handleNextAction() {
    const currentStep = document.querySelector('.booking-step.active').id;

    if (currentStep === 'step0') {
        currentPersonIndex = 1;
        allPersonSelections = [];
        resetCurrentSelections();
        goToStep(1);
    } else if (currentStep === 'step1') {
        if (!globalDate) {
            alert('Please select a date for your appointment');
            return;
        }
        updateStepsUI();
        goToStep(2);
    } else if (currentStep === 'step2') {
        if (selectedServices.length === 0 && !selectedPackage) {
            alert('Please select at least one service or package');
            return;
        }
        generateAssignmentCards();
        goToStep(3);
    } else if (currentStep === 'step3') {
        if (!validateAssignments()) {
            alert('Please assign a specialist and time for all selected services');
            return;
        }

        // Check if any assignment has "Any Available Specialist" (empty staffId)
        const needsAutoAssign = selectedServices.some(item => {
            const itemId = item.instanceId || item.id;
            return !serviceAssignments[itemId]?.staffId;
        });

        if (needsAutoAssign) {
            // Build assignments payload for the API
            const assignmentsPayload = selectedServices.map(item => {
                const itemId = item.instanceId || item.id;
                const assign = serviceAssignments[itemId] || {};
                return {
                    itemId: itemId,
                    service_id: item.id,
                    duration: item.duration || 60,
                    category: item.category || '',
                    staffId: assign.staffId || '',
                    staffName: assign.staffName || '',
                    time: assign.time || '',
                    date: assign.date || globalDate
                };
            });

            // Also include already-saved person assignments to prevent cross-person double booking
            allPersonSelections.forEach(person => {
                Object.keys(person.assignments).forEach(otherId => {
                    const a = person.assignments[otherId];
                    if (a.staffId && a.time) {
                        const personItem = person.services.find(s => (s.instanceId || s.id) === otherId);
                        assignmentsPayload.push({
                            itemId: 'prev_' + otherId,
                            service_id: personItem ? personItem.id : '',
                            duration: personItem ? (personItem.duration || 60) : 60,
                            category: personItem ? (personItem.category || '') : '',
                            staffId: a.staffId,
                            staffName: a.staffName || '',
                            time: a.time,
                            date: a.date || globalDate
                        });
                    }
                });
            });

            // Disable Next button and show loading
            const nextBtn = document.getElementById('btnNextAction');
            if (nextBtn) {
                nextBtn.disabled = true;
                nextBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning Specialist...';
            }

            const formData = new FormData();
            formData.append('date', globalDate);
            formData.append('assignments', JSON.stringify(assignmentsPayload));

            fetch('api/auto-assign-staff.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (nextBtn) {
                        nextBtn.disabled = false;
                        nextBtn.innerHTML = 'Next: Review & Confirm';
                    }

                    if (data.success && data.assignments) {
                        let hasError = false;
                        data.assignments.forEach(result => {
                            // Skip prev_ entries (those are already-saved person assignments)
                            if (result.itemId.startsWith('prev_')) return;

                            if (result.assigned && result.staffId) {
                                // Update serviceAssignments
                                if (serviceAssignments[result.itemId]) {
                                    serviceAssignments[result.itemId].staffId = result.staffId;
                                    serviceAssignments[result.itemId].staffName = result.staffName;
                                }

                                // Update staff dropdown UI
                                const card = document.querySelector(`.assignment-card[data-id="${result.itemId}"]`);
                                if (card) {
                                    const staffSelect = card.querySelector('.staff-select');
                                    if (staffSelect) {
                                        staffSelect.value = result.staffId;
                                        // If the option doesn't exist (shouldn't happen), add it
                                        if (staffSelect.value !== result.staffId) {
                                            const opt = document.createElement('option');
                                            opt.value = result.staffId;
                                            opt.textContent = result.staffName;
                                            staffSelect.appendChild(opt);
                                            staffSelect.value = result.staffId;
                                        }
                                    }
                                }
                            } else if (!result.staffId && result.error) {
                                hasError = true;
                                alert(`Could not assign a specialist for one of the services: ${result.error}. Please manually select a specialist.`);
                            }
                        });

                        if (!hasError) {
                            // Proceed with normal flow
                            proceedAfterStep3();
                        }
                    } else {
                        alert(data.message || 'Error auto-assigning specialist. Please try again.');
                    }
                })
                .catch(err => {
                    console.error('Auto-assign error:', err);
                    if (nextBtn) {
                        nextBtn.disabled = false;
                        nextBtn.innerHTML = 'Next: Review & Confirm';
                    }
                    alert('Error assigning specialist. Please try again.');
                });
        } else {
            // All cards already have specific staff, proceed normally
            proceedAfterStep3();
        }
    }
}

function saveCurrentPersonSelection() {
    // Remove existing entry for this person if any (e.g. if they went back)
    allPersonSelections = allPersonSelections.filter(p => p.personIndex !== currentPersonIndex);

    allPersonSelections.push({
        personIndex: currentPersonIndex,
        services: [...selectedServices],
        package: selectedPackage ? { ...selectedPackage } : null,
        assignments: { ...serviceAssignments }
    });
}

function proceedAfterStep3() {
    // Save current person's selections
    saveCurrentPersonSelection();

    if (currentPersonIndex < paxCount) {
        // Proceed to next person
        currentPersonIndex++;

        if (selectedPackage) {
            // Package booking: each person gets the same services
            const pkgCard = document.querySelector(`.package-mini-card[data-package-id="${selectedPackage.id}"]`);
            const pkgServices = pkgCard ? JSON.parse(pkgCard.dataset.services || '[]') : [];
            selectedServices = pkgServices.map(svc => ({
                id: String(svc.id),
                instanceId: Date.now() + Math.random().toString(36).substr(2, 9),
                duration: parseInt(svc.duration) || 30,
                price: parseFloat(svc.price) || 0,
                name: svc.name,
                category: (svc.category || '').toLowerCase()
            }));
            serviceAssignments = {};
            updateStepsUI();
            generateAssignmentCards();
            goToStep(3);
        } else {
            resetCurrentSelections();
            updateStepsUI();
            goToStep(2);
        }
    } else {
        // All persons done, go to review
        updateBookingSummary();
        goToStep(4);
    }
}

function resetCurrentSelections() {
    selectedServices = [];
    selectedPackage = null;
    serviceAssignments = {};

    // Reset UI elements
    document.querySelectorAll('input[name="services[]"]').forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
        const label = cb.closest('.service-checkbox');
        const controls = label.querySelector('.service-instance-controls');
        if (controls) controls.remove();
    });
    document.querySelectorAll('.package-mini-card').forEach(c => c.classList.remove('selected'));
    updateServiceSummary();
}

function updateStepsUI() {
    const step2Heading = document.getElementById('step2Heading');
    const step3Heading = document.getElementById('step3Heading');

    const personText = paxCount > 1 ? ` (Person ${currentPersonIndex} of ${paxCount})` : '';

    if (step2Heading) step2Heading.innerHTML = `<i class="fas fa-spa"></i> Select Services${personText}`;
    if (step3Heading) step3Heading.innerHTML = `<i class="fas fa-calendar-check"></i> Assign Staff & Time${personText}`;
}

function handlePrevAction() {
    const currentStep = document.querySelector('.booking-step.active').id;

    if (currentStep === 'step1') {
        goToStep(0);
    } else if (currentStep === 'step2') {
        if (currentPersonIndex > 1) {
            // Go back to previous person's assignment
            currentPersonIndex--;
            loadPersonSelection(currentPersonIndex);
            updateStepsUI();
            goToStep(3);
        } else {
            goToStep(1);
        }
    } else if (currentStep === 'step3') {
        if (selectedPackage && currentPersonIndex > 1) {
            // Package booking: go back to previous person's assignment (skip step 2)
            currentPersonIndex--;
            loadPersonSelection(currentPersonIndex);
            updateStepsUI();
            generateAssignmentCards();
            goToStep(3);
        } else {
            goToStep(2);
        }
    } else if (currentStep === 'step4') {
        goToStep(3);
    }
}

function loadPersonSelection(index) {
    const data = allPersonSelections.find(p => p.personIndex === index);
    if (!data) return;

    selectedServices = [...data.services];
    selectedPackage = data.package ? { ...data.package } : null;
    serviceAssignments = { ...data.assignments };

    // Update UI checkboxes
    document.querySelectorAll('input[name="services[]"]').forEach(cb => {
        const svc = selectedServices.find(s => s.id === cb.value);
        cb.checked = !!svc;
        if (selectedPackage) cb.disabled = true;

        if (cb.checked) {
            // No controls needed anymore
        } else {
            const label = cb.closest('.service-checkbox');
            const controls = label.querySelector('.service-instance-controls');
            if (controls) controls.remove();
        }
    });

    // Update Packages UI
    document.querySelectorAll('.package-mini-card').forEach(card => {
        card.classList.toggle('selected', selectedPackage && card.dataset.packageId === selectedPackage.id);
    });

    updateServiceSummary();
}

function goToStep(stepNum) {
    document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
    const target = document.getElementById(`step${stepNum}`);
    if (target) target.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function generateAssignmentCards() {
    const container = document.getElementById('serviceAssignmentsContainer');
    container.innerHTML = '';

    // When starting fresh (person 1), clear all stale temporary selections from previous booking attempts
    if (currentPersonIndex === 1) {
        const cleanupData = new FormData();
        cleanupData.append('action', 'release_all');
        fetch('api/track-selection.php', { method: 'POST', body: cleanupData });
    }

    // Always use selectedServices (packages expand their services into selectedServices)
    const itemsToAssign = selectedServices;

    itemsToAssign.forEach((item, index) => {
        const itemId = item.instanceId || item.id;
        const displayName = item.name + (selectedServices.filter(s => s.id === item.id).length > 1 ? ` (${selectedServices.filter(s => s.id === item.id).indexOf(item) + 1})` : '');

        const card = document.createElement('div');
        card.className = 'assignment-card';
        card.dataset.id = itemId;

        // Find qualified staff for this category
        const category = item.category || '';
        const staffOptions = getStaffOptionsForCategory(category);

        card.innerHTML = `
            <h3><i class="fas fa-magic"></i> ${displayName}</h3>
            <div class="assignment-form">
                <div class="assignment-section">
                    <label>Choose Specialist</label>
                    <select class="staff-select" onchange="onAssignmentChange('${itemId}')">
                        <option value="">Any Available Specialist</option>
                        ${staffOptions}
                    </select>
                </div>
                <!-- Date is now global: ${globalDate} -->
                <div class="assignment-time-slots" id="timeSlots_${itemId}">
                    <!-- Time slots will load here -->
                </div>
            </div>
            <input type="hidden" class="selected-time-input" id="timeInput_${itemId}">
        `;

        container.appendChild(card);

        // Immediately load slots for the global date
        const staffId = serviceAssignments[itemId]?.staffId || '';
        if (serviceAssignments[itemId]) {
            card.querySelector('.staff-select').value = staffId;
        }

        loadTimeSlotsForCard(itemId, staffId, globalDate);
    });
}

function getStaffOptionsForCategory(category) {
    if (!window.staffData || !Array.isArray(window.staffData)) {
        console.error('Staff data not found in window.staffData');
        return '';
    }

    let html = '';
    const normalize = (str) => (str || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]/g, ' ').split(' ').filter(w => w.length > 2).map(w => w.endsWith('s') ? w.slice(0, -1) : w);
    const normalizedCat = normalize(category);

    console.log('Filtering staff for category:', category, 'normalized:', normalizedCat);

    window.staffData.forEach(staff => {
        const id = staff.id;
        const name = `${staff.first_name} ${staff.last_name}`;
        const spec = staff.specialization || '';

        // If no category selected, or it's 'Any Available', include everyone
        if (!category || category === 'any' || normalizedCat.length === 0) {
            html += `<option value="${id}">${name}</option>`;
            return;
        }

        const normalizedSpec = normalize(spec);
        let matches = false;

        // Match if any word in category matches any word in specialization
        normalizedCat.forEach(cWord => {
            normalizedSpec.forEach(sWord => {
                if (sWord === cWord || sWord.includes(cWord) || cWord.includes(sWord)) matches = true;
            });
        });

        if (matches) {
            html += `<option value="${id}">${name}</option>`;
        }
    });

    // If no specific matches found, fallback to showing all staff instead of an empty list
    if (html === '') {
        console.warn('No staff matches for category. Showing all staff.');
        window.staffData.forEach(staff => {
            html += `<option value="${staff.id}">${staff.first_name} ${staff.last_name}</option>`;
        });
    }

    return html;
}

function onAssignmentChange(itemId) {
    const card = document.querySelector(`.assignment-card[data-id="${itemId}"]`);
    if (!card) return;

    // Clear previous time selection so user must re-select for the new staff
    if (serviceAssignments[itemId]) {
        delete serviceAssignments[itemId].time;
    }

    // Clear the hidden time input
    const timeInput = document.getElementById(`timeInput_${itemId}`);
    if (timeInput) timeInput.value = '';

    // Release the old slot on the server
    const releaseData = new FormData();
    releaseData.append('action', 'release');
    releaseData.append('identifier', itemId);
    fetch('api/track-selection.php', { method: 'POST', body: releaseData });

    // Reload time slots for the newly selected staff
    const staffId = card.querySelector('.staff-select').value;
    loadTimeSlotsForCard(itemId, staffId, globalDate);
}

function loadTimeSlotsForCard(itemId, staffId, date) {
    const item = selectedServices.find(s => (s.instanceId || s.id) === itemId);
    const duration = item ? (item.duration || 60) : 60;
    const category = item ? (item.category || '') : '';
    const container = document.getElementById(`timeSlots_${itemId}`);

    container.innerHTML = '<p class="loading">Loading time slots...</p>';

    return fetch(`api/get-time-slots.php?date=${date}&duration=${duration}&staff_id=${staffId}&pax=1&identifier=${itemId}&service_category=${encodeURIComponent(category)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.slots || data.slots.length === 0) {
                container.innerHTML = '<p class="error">No available slots for this date/staff.</p>';
                return;
            }

            // Helper: convert "HH:MM:SS" or "HH:MM" to total minutes
            function timeToMinutes(t) {
                const parts = t.split(':').map(Number);
                return parts[0] * 60 + parts[1];
            }

            // Helper: get all 30-min slot start times blocked by a service at startTime with given duration
            function getBlockedSlots(startTime, serviceDuration) {
                const startMin = timeToMinutes(startTime);
                const slots = [];
                for (let m = startMin; m < startMin + serviceDuration; m += 30) {
                    slots.push(m);
                }
                return slots;
            }

            // Helper: check if proposed slot range [slotStart, slotStart+duration) overlaps with
            // an existing booking range [bookStart, bookStart+bookDuration)
            function rangesOverlap(slotStartMin, slotDuration, bookStartMin, bookDuration) {
                return !(slotStartMin + slotDuration <= bookStartMin || slotStartMin >= bookStartMin + bookDuration);
            }

            // DURATION-AWARE conflict detection:
            // Build lists of blocked time RANGES (not just single start times)
            // staffBlocked: { staffId -> [{start: minutes, duration: minutes}] }
            // clientBlocked: [{start: minutes, duration: minutes}]
            const staffBlocked = {};
            const clientBlocked = [];

            // Helper to find duration for a given assignment's service
            function getServiceDuration(assignItemId) {
                // duration comes from the service item itself
                const svc = selectedServices.find(s => (s.instanceId || s.id) === assignItemId);
                return svc ? (svc.duration || 60) : 60;
            }

            // Check other services in CURRENT person's selection
            Object.keys(serviceAssignments).forEach(otherId => {
                if (otherId !== itemId && serviceAssignments[otherId].date === date) {
                    const t = serviceAssignments[otherId].time;
                    const s = serviceAssignments[otherId].staffId;
                    const d = getServiceDuration(otherId);
                    if (t) {
                        const startMin = timeToMinutes(t);
                        // Block for client (same person can't overlap)
                        clientBlocked.push({ start: startMin, duration: d });
                        // Block for staff
                        if (s) {
                            if (!staffBlocked[s]) staffBlocked[s] = [];
                            staffBlocked[s].push({ start: startMin, duration: d });
                        }
                    }
                }
            });

            // Check ALL other persons' selections (only staff conflicts, not client conflicts)
            allPersonSelections.forEach(person => {
                if (person.personIndex !== currentPersonIndex) {
                    Object.keys(person.assignments).forEach(otherId => {
                        if (person.assignments[otherId].date === date) {
                            const t = person.assignments[otherId].time;
                            const s = person.assignments[otherId].staffId;
                            // Find service duration for this person's assignment
                            const personItems = person.package ? [person.package] : person.services;
                            const personItem = personItems.find(pi => (pi.instanceId || pi.id) === otherId);
                            const d = personItem ? (personItem.duration || 60) : 60;
                            if (t && s) {
                                const startMin = timeToMinutes(t);
                                if (!staffBlocked[s]) staffBlocked[s] = [];
                                staffBlocked[s].push({ start: startMin, duration: d });
                            }
                        }
                    });
                }
            });

            let html = `
                <div class="time-slots-selection">
                    <label>Available Slots</label>
                    <div class="time-slots-grid">`;

            data.slots.forEach(slot => {
                const isSelected = serviceAssignments[itemId]?.time === slot.start;
                const slotStartMin = timeToMinutes(slot.start);

                // Check if this slot's DURATION RANGE overlaps with any staff blocked range
                let isStaffBusyInThisBooking = false;
                if (staffId && staffBlocked[staffId]) {
                    for (const blocked of staffBlocked[staffId]) {
                        if (rangesOverlap(slotStartMin, duration, blocked.start, blocked.duration)) {
                            isStaffBusyInThisBooking = true;
                            break;
                        }
                    }
                }

                // Check if this slot's DURATION RANGE overlaps with any client blocked range
                let isClientTimeTaken = false;
                for (const blocked of clientBlocked) {
                    if (rangesOverlap(slotStartMin, duration, blocked.start, blocked.duration)) {
                        isClientTimeTaken = true;
                        break;
                    }
                }

                if (isClientTimeTaken && !isSelected) {
                    html += `<div class="time-slot disabled" title="You already have a service booked at this time">${slot.display}</div>`;
                } else if (isStaffBusyInThisBooking && !isSelected) {
                    html += `<div class="time-slot disabled" title="Specialist already assigned to another service at this time">${slot.display}</div>`;
                } else if (!slot.is_available && !isSelected) {
                    html += `<div class="time-slot disabled" title="Slot already booked or unavailable">${slot.display}</div>`;
                } else {
                    html += `<div class="time-slot ${isSelected ? 'selected' : ''}" data-time="${slot.start}" onclick="selectTimeForCard('${itemId}', '${slot.start}', this)">${slot.display}</div>`;
                }
            });

            html += `</div></div>
                <div class="time-slots-summary" style="display:none">
                    <div class="selected-time-info">
                        <i class="fas fa-check-circle"></i> <span></span>
                    </div>
                    <button type="button" class="btn-edit-time" onclick="editTimeSlot('${itemId}')">Change Time</button>
                </div>`;

            container.innerHTML = html;

            // If already selected, collapse it
            if (serviceAssignments[itemId]?.time) {
                collapseTimeSlots(itemId, serviceAssignments[itemId].time);
            }
        });
}

function selectTimeForCard(itemId, time, el) {
    if (el.classList.contains('disabled')) return;

    const grid = el.parentElement;
    grid.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');

    const card = document.querySelector(`.assignment-card[data-id="${itemId}"]`);
    const staffSelect = card.querySelector('.staff-select');
    const staffId = staffSelect.value;
    const date = globalDate;

    // Get the service duration for tracking
    const item = selectedServices.find(s => (s.instanceId || s.id) === itemId);
    const duration = item ? (item.duration || 60) : 60;

    serviceAssignments[itemId] = {
        staffId,
        staffName: staffSelect.options[staffSelect.selectedIndex].text,
        date,
        time
    };

    const timeInput = document.getElementById(`timeInput_${itemId}`);
    if (timeInput) timeInput.value = time;

    // Notify server of selection (with duration for range-aware temp blocking)
    const formData = new FormData();
    formData.append('action', 'select');
    formData.append('staff_id', staffId || '');
    formData.append('date', date);
    formData.append('time', time);
    formData.append('duration', duration);
    formData.append('identifier', itemId);
    fetch('api/track-selection.php', { method: 'POST', body: formData });

    // Collapse after selection
    collapseTimeSlots(itemId, time);

    // Refresh other cards if they are on the same date to update conflicts
    // and apply AI suggestion to the NEXT unassigned card
    const allCards = Array.from(document.querySelectorAll('.assignment-card'));
    const currentCardIndex = allCards.findIndex(c => c.dataset.id === itemId);

    allCards.forEach((otherCard, idx) => {
        const otherId = otherCard.dataset.id;
        if (otherId !== itemId) {
            const otherStaff = otherCard.querySelector('.staff-select').value;
            loadTimeSlotsForCard(otherId, otherStaff, globalDate).then(() => {
                // Apply AI suggestion to the next unassigned card
                if (idx === currentCardIndex + 1 && !serviceAssignments[otherId]?.time) {
                    applyAISuggestion(itemId, otherId, time, globalDate);
                }
            });
        }
    });
}


function applyAISuggestion(fromItemId, toItemId, fromTime, date) {
    // Find the duration of the 'from' service to calculate the earliest next slot
    const fromItem = selectedServices.find(s => (s.instanceId || s.id) === fromItemId);
    const fromDuration = fromItem ? fromItem.duration || 60 : 60;

    // Earliest the next service can start = fromTime + fromDuration
    const [h, m] = fromTime.split(':').map(Number);
    const fromMinutes = h * 60 + m + fromDuration;
    const earliestH = Math.floor(fromMinutes / 60);
    const earliestM = fromMinutes % 60;
    const earliestTime = `${String(earliestH).padStart(2, '0')}:${String(earliestM).padStart(2, '0')}:00`;

    // Find the first available slot at or after earliestTime in the next card's grid
    const container = document.getElementById(`timeSlots_${toItemId}`);
    if (!container) return;

    // Clear previous suggestions
    container.querySelectorAll('.time-slot.suggested').forEach(s => {
        s.classList.remove('suggested');
        s.title = s.title.replace(' — AI Suggested', '');
    });

    const slots = container.querySelectorAll('.time-slot:not(.disabled)');
    let suggested = false;
    slots.forEach(slot => {
        if (!suggested) {
            const slotTime = slot.dataset.time;
            if (slotTime && slotTime >= earliestTime) {
                slot.classList.add('suggested');
                slot.title = (slot.title ? slot.title + ' — ' : '') + 'AI Suggested';
                suggested = true;
                // Scroll the slot into view gently
                slot.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });
}

function showAIDateHint(card, suggestedDate) {
    // Show a small hint banner on the next card suggesting to use the same date
    let hint = card.querySelector('.ai-date-hint');
    if (!hint) {
        hint = document.createElement('div');
        hint.className = 'ai-date-hint';
        card.querySelector('.assignment-form').prepend(hint);
    }
    hint.innerHTML = `<i class="fas fa-robot"></i> AI Tip: Consider booking on <strong>${formatDate(suggestedDate)}</strong> to keep your appointments together.`;
}

function collapseTimeSlots(itemId, time) {
    const container = document.getElementById(`timeSlots_${itemId}`);
    if (!container) return;

    const selectionDiv = container.querySelector('.time-slots-selection');
    const summaryDiv = container.querySelector('.time-slots-summary');

    if (selectionDiv && summaryDiv) {
        selectionDiv.style.display = 'none';
        summaryDiv.style.display = 'flex';

        const displayTime = formatTime(time);
        summaryDiv.querySelector('.selected-time-info span').innerHTML = `<strong>Selected Time:</strong> ${displayTime}`;
    }
}

function editTimeSlot(itemId) {
    const container = document.getElementById(`timeSlots_${itemId}`);
    if (!container) return;

    // Clear the previous time assignment so user must re-select
    if (serviceAssignments[itemId]) {
        delete serviceAssignments[itemId].time;
    }

    // Clear the hidden time input
    const timeInput = document.getElementById(`timeInput_${itemId}`);
    if (timeInput) timeInput.value = '';

    // Notify server of release
    const formData = new FormData();
    formData.append('action', 'release');
    formData.append('identifier', itemId);
    fetch('api/track-selection.php', { method: 'POST', body: formData });

    // Reload time slots to refresh availability and clear the old selection
    const card = document.querySelector(`.assignment-card[data-id="${itemId}"]`);
    const staffId = card ? card.querySelector('.staff-select').value : '';
    loadTimeSlotsForCard(itemId, staffId, globalDate);
}

function validateAssignments() {
    const items = selectedServices;
    for (const item of items) {
        const itemId = item.instanceId || item.id;
        if (!serviceAssignments[itemId] || !serviceAssignments[itemId].time) {
            return false;
        }
    }
    return true;
}

function updateBookingSummary() {
    const servicesDiv = document.getElementById('summaryServices');
    let html = '';

    allPersonSelections.forEach(personData => {
        const personIndex = personData.personIndex;
        const items = personData.services;
        const assignments = personData.assignments;

        // Calculate person subtotal
        const personSubtotal = personData.package
            ? (personData.package.price || 0)
            : personData.services.reduce((sum, s) => sum + (s.price || 0), 0);

        html += `<div class="summary-person-group">`;
        // Always show person header (for single pax it shows Person 1 as a clear container)
        const personLabel = paxCount > 1 ? `Person ${personIndex}` : 'Your Appointment';
        html += `<div class="person-group-header"><i class="fas fa-user-circle"></i> ${personLabel}</div>`;

        items.forEach(item => {
            const itemId = item.instanceId || item.id;
            const assign = assignments[itemId];
            const displayName = item.name + (personData.services && personData.services.filter(s => s.id === item.id).length > 1 ? ` (${personData.services.filter(s => s.id === item.id).indexOf(item) + 1})` : '');
            const priceDisplay = item.price ? formatPrice(item.price) : '';

            html += `
                <div class="summary-card">
                    <div class="summary-card-header">
                        <h4><i class="fas fa-spa"></i> ${displayName}</h4>
                        ${priceDisplay ? `<span class="summary-card-price">${priceDisplay}</span>` : ''}
                    </div>
                    <div class="summary-card-body">
                        <div class="summary-detail">
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Specialist</span>
                            <span class="detail-value">${assign.staffName}</span>
                        </div>
                        <div class="summary-detail">
                            <span class="detail-label"><i class="fas fa-calendar-day"></i> Date</span>
                            <span class="detail-value">${formatDate(assign.date)}</span>
                        </div>
                        <div class="summary-detail">
                            <span class="detail-label"><i class="fas fa-clock"></i> Time</span>
                            <span class="detail-value">${formatTime(assign.time)}</span>
                        </div>
                    </div>
                </div>
            `;
        });

        if (personSubtotal > 0) {
            html += `<div class="person-group-subtotal"><span>Subtotal for ${personLabel}</span><span>${formatPrice(personSubtotal)}</span></div>`;
        }

        html += `</div>`;
    });

    servicesDiv.innerHTML = html;

    // Appointment details summary
    document.getElementById('summaryAppointment').innerHTML = `
        <div class="summary-info-card">
            <div class="summary-detail">
                <span class="detail-label"><i class="fas fa-users"></i> Total Pax</span>
                <span class="detail-value">${paxCount} ${paxCount > 1 ? 'Persons' : 'Person'}</span>
            </div>
            <div class="summary-detail">
                <span class="detail-label"><i class="fas fa-info-circle"></i> Status</span>
                <span class="detail-value status-reserved">Reserved</span>
            </div>
        </div>
    `;

    calculateFinalPrice();
}

function calculateFinalPrice() {
    let allServiceIds = [];
    let packageIds = [];

    allPersonSelections.forEach(p => {
        if (p.package) {
            // Package selected — use package pricing (don't add individual service IDs to avoid double-count)
            packageIds.push(p.package.id);
        } else {
            // Individual services — add their IDs for pricing
            allServiceIds = allServiceIds.concat(p.services.map(s => s.id));
        }
    });

    // Note: The API might need update to handle multiple packages if that's possible, 
    // but for now we follow the existing pattern as much as possible.
    // If each person can have a package, we might need to adjust create-booking.php too.

    fetch('api/calculate-price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            services: allServiceIds,
            package_id: packageIds.length > 0 ? packageIds[0] : null, // Current API might only take one
            package_ids: packageIds, // Added for future proofing
            pax: paxCount
        })
    })
        .then(r => r.json())
        .then(data => {
            document.getElementById('summaryPricing').innerHTML = `
            <div class="pricing-summary-card">
                <div class="summary-detail">
                    <span class="detail-label">Subtotal</span>
                    <span class="detail-value">${formatPrice(data.subtotal)}</span>
                </div>
                ${data.discount > 0 ? `
                <div class="summary-detail discount">
                    <span class="detail-label">Special Discount</span>
                    <span class="detail-value">-${formatPrice(data.discount)}</span>
                </div>` : ''}
                <div class="summary-total">
                    <span class="total-label">Total Amount</span>
                    <span class="total-value">${formatPrice(data.total)}</span>
                </div>
            </div>
        `;
        });
}

document.getElementById('bookingForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    // Prepare extended data
    let allDetailedAssignments = [];
    allPersonSelections.forEach(p => {
        const items = p.services;
        items.forEach(item => {
            const itemId = item.instanceId || item.id;
            allDetailedAssignments.push({
                person_index: p.personIndex,
                service_id: item.id,
                service_name: item.name,
                ...p.assignments[itemId]
            });
        });
    });

    // We'll use the earliest date/time as the "main" one for the appointments table
    const mainAssignment = allDetailedAssignments.sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time))[0];

    const allServiceIds = [];
    allPersonSelections.forEach(p => p.services.forEach(s => allServiceIds.push(s.id)));
    formData.append('services', JSON.stringify(allServiceIds));

    const allPackageIds = allPersonSelections.filter(p => p.package).map(p => p.package.id);
    if (allPackageIds.length > 0) formData.append('selected_package', allPackageIds[0]);
    formData.append('package_ids', JSON.stringify(allPackageIds));

    formData.append('staff_id', mainAssignment.staffId);
    formData.append('appointment_date', mainAssignment.date);
    formData.append('appointment_time', mainAssignment.time);
    formData.append('pax', paxCount);
    formData.append('client_details', JSON.stringify(allDetailedAssignments));

    fetch('api/create-booking.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = `booking-success.php?id=${data.appointment_id}`;
            } else {
                alert(data.message || 'Error creating booking.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error creating booking. Please try again.');
        });
});

function formatPrice(p) { return '₱' + parseFloat(p).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d) { return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr % 12 || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}
