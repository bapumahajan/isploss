<?php
// Add logic to fetch the circuit_id from the URL
if (isset($_GET['circuit_id'])) {
    $circuit_id = $_GET['circuit_id'];
    // Fetch the relevant details from both tables (customer_basic_information and network_details)
    $customer_query = $conn->prepare("SELECT * FROM customer_basic_information WHERE circuit_id = ?");
    $customer_query->bind_param("s", $circuit_id);
    $customer_query->execute();
    $customer_result = $customer_query->get_result();
    $customer_data = $customer_result->fetch_assoc();

    $network_query = $conn->prepare("SELECT * FROM network_details WHERE circuit_id = ?");
    $network_query->bind_param("s", $circuit_id);
    $network_query->execute();
    $network_result = $network_query->get_result();
    $network_data = $network_result->fetch_assoc();

    // Continue with form rendering for editing the data
}
