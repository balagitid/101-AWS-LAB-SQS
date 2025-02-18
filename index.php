<?php
require 'vendor/autoload.php';

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// AWS Credentials
$awsKey = $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
$awsSecret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
$awsRegion = $_ENV['AWS_REGION'] ?? 'us-east-1';
$queueUrl = $_ENV['SQS_QUEUE_URL'] ?? null;

if (!$awsKey || !$awsSecret || !$queueUrl || !$awsRegion) {
    die("⚠️ ERROR: Missing required AWS environment variables.");
}

// Initialize AWS SQS Client
$client = new SqsClient([
    'region' => $awsRegion,
    'version' => 'latest',
    'credentials' => [
        'key'    => $awsKey,
        'secret' => $awsSecret,
    ],
]);

$messageSent = false;
$sentMessageText = "";
$receivedMessages = [];
$messageCount = 0;

// Get the number of messages in the queue
try {
    $attributes = $client->getQueueAttributes([
        'QueueUrl'       => $queueUrl,
        'AttributeNames' => ['ApproximateNumberOfMessages'],
    ]);
    $messageCount = (int) $attributes->get('Attributes')['ApproximateNumberOfMessages'];
} catch (AwsException $e) {
    echo "<div class='alert alert-danger'>Error fetching queue attributes: " . $e->getMessage() . "</div>";
}

// Handle Send Message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send'])) {
    $message = $_POST['message'] ?? '';

    if (!empty($message)) {
        try {
            $result = $client->sendMessage([
                'QueueUrl'    => $queueUrl,
                'MessageBody' => $message,
            ]);

            if ($result['MessageId']) {
                $messageSent = true;
                $sentMessageText = $message;
            } else {
                echo "<div class='alert alert-danger'>⚠️ Failed to send message!</div>";
            }
        } catch (AwsException $e) {
            echo "<div class='alert alert-danger'>Error sending message: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>⚠️ Please enter a message!</div>";
    }
}

// Handle Receive Messages
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive'])) {
    try {
        $result = $client->receiveMessage([
            'QueueUrl' => $queueUrl,
            'MaxNumberOfMessages' => 5,
            'WaitTimeSeconds' => 2,
        ]);

        if (!empty($result->get('Messages'))) {
            foreach ($result->get('Messages') as $message) {
                $receivedMessages[] = $message['Body'];

                // Delete message after processing
                $client->deleteMessage([
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ]);
            }
        } else {
            echo "<div class='alert alert-warning'>⚠️ No messages available in the queue!</div>";
        }
    } catch (AwsException $e) {
        echo "<div class='alert alert-danger'>Error receiving message: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWS CLOUD DEMO LABS: SQS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .header-container {
            background-color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
        }
        .logo {
            max-height: 60px;
        }
        .refresh-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .refresh-btn:hover {
            background-color: #218838;
        }
        .marquee-container {
            background-color: #007BFF;
            color: white;
            font-size: 20px;
            font-weight: bold;
            padding: 10px;
            text-align: center;
        }
        .description {
            background-color: #f8f9fa;
            padding: 15px;
            margin-top: 10px;
            font-size: 16px;
            border-radius: 5px;
        }
        footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 10px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <img src="https://kubelancerlogopublic.s3.us-east-1.amazonaws.com/Kubelancer+Logo-3.png" alt="Kubelancer Logo" class="logo">
        <button class="refresh-btn" onclick="window.location.href=window.location.pathname;">🔄 Refresh Page</button>
    </div>

    <div class="marquee-container">
        <marquee behavior="scroll" direction="left">AWS CLOUD DEMO LABS: SQS - Learn, Experiment, and Innovate with Amazon Simple Queue Service (SQS)</marquee>
    </div>

    <!-- Lab Description -->
    <div class="container">
        <div class="description">
            <h4>How this AWS SQS Demo Lab Works</h4>
            <ol>
                <li>This lab demonstrates how to send, receive, and manage messages using Amazon Simple Queue Service (SQS).</li>
                <li>It allows you to test event-driven messaging in the cloud.</li>
                <li>Send a message to the queue using the form below.</li>
                <li>Retrieve messages from the queue when available.</li>
                <li>Messages are automatically deleted from the queue after being processed.</li>
            </ol>
        </div>
    </div>

    <div class="container mt-3">
        <div class="alert alert-info">
            📊 Queue contains <strong><?php echo $messageCount; ?></strong> messages.
        </div>

        <!-- Display success message for sent message -->
        <?php if ($messageSent): ?>
            <div class="alert alert-success">✅ Message sent successfully: <strong><?php echo htmlspecialchars($sentMessageText); ?></strong></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label class="form-label">Enter Message:</label>
                <input type="text" name="message" class="form-control" required>
            </div>
            <button type="submit" name="send" class="btn btn-primary">📩 Send to SQS</button>
        </form>

        <form method="POST">
            <?php if ($messageCount > 0): ?>
                <button type="submit" name="receive" class="btn btn-success">📥 Receive Messages</button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled>Queue is Empty ❌</button>
            <?php endif; ?>
        </form>

        <?php if (!empty($receivedMessages)): ?>
            <div class="mt-4">
                <h4>📬 Received Messages:</h4>
                <ul class="list-group">
                    <?php foreach ($receivedMessages as $msg): ?>
                        <li class="list-group-item"><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>© 2025 Kubelancer Private Limited | <a href="https://kubelancer.com" target="_blank">Visit Website</a></p>
        <p>📩 Contact us at <a href="mailto:connect@kubelancer.com">connect@kubelancer.com</a></p>
    </footer>

</body>
</html>
