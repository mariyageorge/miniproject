<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com'; // Replace with your SMTP host
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'lifesyncdigital@gmail.com'; // Replace with your email
        $this->mail->Password = 'yrpw iqys blcl famq'; // Replace with your app password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Default sender
        $this->mail->setFrom('your-email@gmail.com', 'LIFE-SYNC');
    }
    
    public function sendGroupInvitation($to_email, $to_name, $group_name, $invitation_link) {
        try {
            $this->mail->addAddress($to_email, $to_name);
            $this->mail->isHTML(true);
            $this->mail->Subject = "You're invited to join $group_name on LIFE-SYNC";
            
            // Email body
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #8B4513;'>Hello $to_name!</h2>
                    <p>You've been invited to join the expense splitting group <strong>$group_name</strong> on LIFE-SYNC.</p>
                    <p>Click the button below to join the group and start splitting expenses with your friends:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$invitation_link' 
                           style='background-color: #8B4513; 
                                  color: white; 
                                  padding: 12px 24px; 
                                  text-decoration: none; 
                                  border-radius: 5px;
                                  display: inline-block;'>
                            Join Group
                        </a>
                    </div>
                    <p style='color: #666; font-size: 14px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='color: #666; font-size: 14px;'>$invitation_link</p>
                    <hr style='border: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>This is an automated message from LIFE-SYNC. Please do not reply to this email.</p>
                </div>
            ";
            
            $this->mail->Body = $body;
            $this->mail->AltBody = "You've been invited to join $group_name on LIFE-SYNC. Click here to join: $invitation_link";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
} 