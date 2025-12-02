<?php
// includes/modals.php
?>
<!-- Complaint Handling Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complaint Handling</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe id="complaintModalFrame" src="" width="100%" height="600px" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Raise Complaint Modal -->
<div class="modal fade" id="raiseComplaintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Raise Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="complaints_portal.php" width="100%" height="500px" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Alert Sound -->
<audio id="alertSound">
    <source src="https://www.soundjay.com/buttons/sounds/beep-07.mp3" type="audio/mpeg">
</audio>
