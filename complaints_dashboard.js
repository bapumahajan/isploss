let playedSoundIds = [];

function openComplaintHandle(id) {
    const url = "complaints_portal_dashboard.php?circuit_id=" + encodeURIComponent(id);
    document.getElementById('complaintModalFrame').src = url;
    const myModal = new bootstrap.Modal(document.getElementById('complaintModal'));
    myModal.show();
}

function openRaiseComplaintModal() {
    const myModal = new bootstrap.Modal(document.getElementById('raiseComplaintModal'));
    myModal.show();
}

function startTimer(elementId, bookingTime) {
    const timerElement = document.getElementById(elementId);
    if (!timerElement) return;

    function updateTimer() {
        const now = new Date().getTime();
        const elapsedMs = now - bookingTime;

        const totalSeconds = Math.floor(elapsedMs / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        if (totalSeconds < 600) {
            timerElement.className = 'timer green';
        } else if (totalSeconds < 900) {
            timerElement.className = 'timer yellow';
        } else {
            timerElement.className = 'timer red';
            if (!playedSoundIds.includes(elementId)) {
                document.getElementById('alertSound').play();
                playedSoundIds.push(elementId);
            }
        }
    }

    updateTimer();
    setInterval(updateTimer, 1000);
}

// Optional: auto-refresh page every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
