<?php
namespace App\Services;

// Importation manuelle des fichiers PHPMailer (sans Composer)
require_once __DIR__ . '/../../core/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../../core/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../core/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * SERVICE EMAIL — Gestion des envois de mails via PHPMailer & Gmail
 */
class ServiceEmail {

    public static function envoyerRecuperation(string $email, string $token): bool {
        $lien = "http://localhost:5173/reset-password/" . $token;
        $sujet = "Réinitialisation de votre mot de passe — Stratis";
        
        $message = "
        <html>
        <body style=\"font-family: Arial, sans-serif; color: #333;\">
            <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;\">
                <h2 style=\"color: #2563EB;\">STRA<em>TIS</em></h2>
                <p>Bonjour,</p>
                <p>Pour réinitialiser votre mot de passe, veuillez cliquer sur le bouton ci-dessous :</p>
                <p style=\"text-align: center;\">
                    <a href='$lien' style=\"display: inline-block; padding: 12px 24px; background-color: #2563EB; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;\">Réinitialiser mon mot de passe</a>
                </p>
                <p>Ce lien est valable 1 heure.</p>
                <p>Si vous n'êtes pas à l'origine de cette demande, ignorez ce mail.</p>
                <hr style=\"border: 0; border-top: 1px solid #eee; margin: 20px 0;\">
                <p style=\"font-size: 12px; color: #999;\">Ceci est un message automatique, merci de ne pas y répondre.</p>
            </div>
        </body>
        </html>";

        return self::envoyer($email, $sujet, $message);
    }

    private static function envoyer(string $to, string $subject, string $htmlContent): bool {
        $mail = new PHPMailer(true);

        try {
            // Configuration du serveur SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            
            // Gmail recommande souvent le Port 587 avec TLS pour les clients locaux
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;

            // Destinataires
            $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
            $mail->addAddress($to);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlContent;
            $mail->CharSet = 'UTF-8';

            $mail->send();
            return true;
        } catch (Exception $e) {
            // ÉCRITURE DE L'ERREUR DANS UN FICHIER DE LOG LOCAL
            $log = "[" . date('Y-m-d H:i:s') . "] Erreur PHPMailer : " . $mail->ErrorInfo . "\n";
            file_put_contents(__DIR__ . '/../../logs_email.txt', $log, FILE_APPEND);
            return false;
        }
    }
}
