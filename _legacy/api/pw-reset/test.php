<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mreagle13337@gmail.com';       // Your Gmail address
    $mail->Password   = 'effq jzqi vdga uegu';    // The app password you generated
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
    $mail->Port       = 587;                    // TLS port
    
    // Recipients
    $mail->setFrom('no-reply@compilecrew.xyz', 'Your Name');
    $mail->addAddress('elffcker@gmail.com', 'Recipient Name');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Gmail SMTP';
    $mail->Body    = 'This is a test email sent through Gmail SMTP';
    
    $mail->send();
    echo 'Email has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Error: {$mail->ErrorInfo}";
}