<?php
//  CipherTrust CRDP PHP Application (for Kubernetes with No Authentication)
//  This script provides a simple web interface to interact with a
//  CipherTrust RESTful Data Protection (CRDP) service.
//  This version assumes the CRDP endpoint requires no authentication.

// --- Configuration ---
// Replace these values with your CRDP service details.

// The full URL to your CRDP service endpoint.
// If running locally, you can use 'http://localhost:8080' or similar.
$crdp_service_url = 'http://ENTER_YOUR_URL';

// The name of the Protection Policy you want to use for data protection.
// This policy must be available to the CRDP service from the connected CipherTrust Manager.
$protection_policy = 'generic';

// --- End of Configuration ---

$input_data = '';
$api_response = '';
$error_message = '';
$username = '';
$request_endpoint = '';
$request_payload = '';
$successful_response_json = '';

// Main logic to handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the form
    $input_data = isset($_POST['input_data']) ? $_POST['input_data'] : '';
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'protect';
    $username = isset($_POST['username']) ? $_POST['username'] : '';

    if (empty($input_data)) {
        $error_message = 'Please enter some data to process.';
    } elseif (empty($crdp_service_url) || $crdp_service_url === 'http://ENTER_YOUR_URL' && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        $error_message = 'CRDP Service URL is not configured. Please edit the PHP script.';
    } else {
        // Perform the data protection operation by calling the CRDP API
        $api_result = call_crdp_api($crdp_service_url, $protection_policy, $input_data, $operation, $username);

        // Capture request details for display
        $request_endpoint = isset($api_result['endpoint']) ? $api_result['endpoint'] : '';
        if (isset($api_result['payload'])) {
            // Pretty-print the JSON payload for display
            $request_payload = json_encode(json_decode($api_result['payload']), JSON_PRETTY_PRINT);
        }

        if (isset($api_result['error'])) {
            $error_message = 'API Error: ' . htmlspecialchars($api_result['error']);
            // If there was a response body with the error, show it
            if (isset($api_result['response'])) {
                 $api_response = is_string($api_result['response']) ? $api_result['response'] : json_encode($api_result['response'], JSON_PRETTY_PRINT);
            }
        } else {
            // Display the successful result from the API and prepare for the copy button
            $successful_response_json = json_encode($api_result['response']);
            $api_response = json_encode($api_result['response'], JSON_PRETTY_PRINT);
        }
    }
}

/**
 * Calls the CRDP API to protect or reveal data.
 *
 * @param string $base_url The base URL of the CRDP service.
 * @param string $policy The name of the protection policy.
 * @param string $data The data to process.
 * @param string $operation The operation ('protect' or 'reveal').
 * @param string $username The username for reveal operations.
 * @return array An array containing the API response, endpoint, and payload.
 */
function call_crdp_api($base_url, $policy, $data, $operation, $username) {
    // The API endpoint is now just for the operation type (protect/reveal).
    // e.g., http://crdpdemo/v1/protect
    $api_endpoint = rtrim($base_url, '/') . '/v1/' . rawurlencode($operation);

    // The protection policy is sent in the JSON body.
    $payload_array = [
        'protection_policy_name' => $policy,
    ];

    // Use 'protected_data' as the key for reveal operations, and 'data' for protect operations.
    // Also include the username for reveal operations.
    if ($operation === 'reveal') {
        $payload_array['protected_data'] = $data;
        $payload_array['username'] = $username;
    } else {
        $payload_array['data'] = $data;
    }

    $payload = json_encode($payload_array);

    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
        // No Authorization header is sent in this version.
    ]);
    // IMPORTANT: In a production environment, you should not disable SSL verification.
    // You should configure cURL to trust your CRDP service's certificate.
    // For development/testing only:
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $return_data = [
        'endpoint' => $api_endpoint,
        'payload' => $payload
    ];

    if ($curl_error) {
        $return_data['error'] = 'cURL Error: ' . $curl_error;
        return $return_data;
    }

    if ($http_code >= 200 && $http_code < 300) {
        $return_data['response'] = json_decode($response, true);
    } else {
        $return_data['error'] = 'API returned HTTP status ' . $http_code;
        $return_data['response'] = $response; // Include raw response on error
    }
    
    return $return_data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CipherTrust CRDP PHP App (No Auth)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-800 flex items-center justify-center min-h-screen py-12">
    <div class="w-full max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8">
        <div class="flex justify-center mb-6">
            <img src="images/Thales_LOGO_RGB.png" alt="Thales Logo" class="h-12" style="width:500px;height:200px;" onerror="this.style.display='none'">
        </div>
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-2">CipherTrust RESTful Data Protection (CRDP)</h1>
        <p class="text-center text-gray-500 mb-6">Send data to a CRDP service running in Kubernetes.</p>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                <strong class="font-bold">Error:</strong>
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-6">
                <label for="input_data" class="block text-gray-700 text-sm font-bold mb-2">Data to Process:</label>
                <textarea id="input_data" name="input_data" rows="6" class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" required><?php echo htmlspecialchars($input_data); ?></textarea>
            </div>

            <div class="mb-6">
                <label for="operation" class="block text-gray-700 text-sm font-bold mb-2">Operation:</label>
                <select id="operation" name="operation" class="shadow-sm border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="protect">Protect (Encrypt/Tokenize)</option>
                    <option value="reveal">Reveal (Decrypt/Detokenize)</option>
                </select>
            </div>

            <div id="username-field" class="mb-6" style="display: none;">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username (for Reveal):</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-center justify-center">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Process Data
                </button>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="mt-8 pt-6 border-t border-gray-200 space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Request Details:</h2>
                    <div class="bg-gray-800 text-white rounded-lg p-4 overflow-x-auto">
                        <h3 class="font-semibold text-gray-400">Endpoint URL:</h3>
                        <pre class="mb-4"><code><?php echo htmlspecialchars($request_endpoint); ?></code></pre>
                        <h3 class="font-semibold text-gray-400">JSON Payload:</h3>
                        <pre><code><?php echo htmlspecialchars($request_payload); ?></code></pre>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <h2 class="text-xl font-bold text-gray-800">API Response:</h2>
                        <?php if (!empty($successful_response_json)): ?>
                            <button type="button" id="copy-response-btn" data-response='<?php echo htmlspecialchars($successful_response_json, ENT_QUOTES, 'UTF-8'); ?>' class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-bold py-1 px-3 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                                Copy to Input
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="bg-gray-800 text-white rounded-lg p-4 overflow-x-auto">
                        <pre><code><?php echo htmlspecialchars($api_response); ?></code></pre>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const operationSelect = document.getElementById('operation');
        const usernameField = document.getElementById('username-field');
        const copyBtn = document.getElementById('copy-response-btn');

        function toggleUsernameField() {
            if (operationSelect.value === 'reveal') {
                usernameField.style.display = 'block';
            } else {
                usernameField.style.display = 'none';
            }
        }

        // Add event listener for operation changes
        operationSelect.addEventListener('change', toggleUsernameField);

        // Add event listener for the copy button
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                try {
                    const responseData = JSON.parse(copyBtn.dataset.response);
                    // Get data from 'protected_data' (after a protect op) or 'data' (after a reveal op)
                    const dataToCopy = responseData.protected_data || responseData.data || '';
                    
                    if (dataToCopy) {
                        document.getElementById('input_data').value = dataToCopy;
                        
                        // Visual feedback
                        const originalText = copyBtn.textContent;
                        copyBtn.textContent = 'Copied!';
                        copyBtn.disabled = true;
                        setTimeout(() => {
                            copyBtn.textContent = originalText;
                            copyBtn.disabled = false;
                        }, 1500);
                    }
                } catch (e) {
                    console.error("Failed to copy response data:", e);
                }
            });
        }

        // Call on page load to set the initial state correctly
        document.addEventListener('DOMContentLoaded', toggleUsernameField);
    </script>
</body>
</html>

