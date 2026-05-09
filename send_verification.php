<?php
/**
 * send_verification.php
 * ─────────────────────
 * Sends an account-verification email to a newly registered user.
 *
 * Requires either:
 *   • PHPMailer  (composer require phpmailer/phpmailer)  ← recommended
 *   • Or falls back to PHP's built-in mail() function
 *
 * ── Configuration ────────────────────────────────────────────────────────────
 * Adjust the constants below to match your SMTP provider
 * (Gmail, Mailgun, SendGrid, etc.).
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── CONFIGURE THESE ───────────────────────────────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');          // SMTP host
define('MAIL_PORT',       587);                       // 587 = TLS / 465 = SSL
define('MAIL_USERNAME',   'smartedu.fr@gmail.com');    // SMTP login
define('MAIL_PASSWORD',   'fqxb minm zsji wwcd');       // App password (not your real password)
define('MAIL_FROM',       'smartedu.fr@gmail.com');    // From address
define('MAIL_FROM_NAME',  'SmartEdu');                // From name
define('APP_BASE_URL',    'http://localhost/SMARTEDU');  // No trailing slash
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @param  string $toEmail   Recipient's email address
 * @param  string $username  Recipient's username (for personalisation)
 * @param  string $token     64-char hex verification token
 * @return bool              true on success, false on failure
 */
function sendVerificationEmail(string $toEmail, string $username, string $token): bool
{
    $verifyUrl = APP_BASE_URL . '/verify.php?token=' . urlencode($token);

    // ── Try PHPMailer first ───────────────────────────────────────────────────
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            // Recipients
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $username);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Verify your SmartEdu account';
            $mail->Body    = buildEmailHtml($username, $verifyUrl);
            $mail->AltBody = buildEmailText($username, $verifyUrl);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Fallback: PHP mail() ──────────────────────────────────────────────────
    $subject = 'Verify your SmartEdu account';
    $body    = buildEmailText($username, $verifyUrl);
    $headers = implode("\r\n", [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    return mail($toEmail, $subject, $body, $headers);
}

// ── Email templates ───────────────────────────────────────────────────────────

function buildEmailHtml(string $username, string $verifyUrl): string
{
    $u = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $v = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Verify your SmartEdu account</title>
</head>
<body style="margin:0;padding:0;background:#ced4df;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#ced4df;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#061685;border-radius:20px;overflow:hidden;max-width:560px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="padding:32px 40px 24px;text-align:center;">
              <p style="margin:0;font-size:28px;font-weight:700;color:#FBFCFF;letter-spacing:-0.5px;">SmartEdu</p>
              <p style="margin:8px 0 0;font-size:13px;color:rgba(251,252,255,0.65);">Your academic journey starts here</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:0 40px 32px;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.08);border-radius:16px;overflow:hidden;">
                <tr>
                  <td style="padding:28px 28px 24px;">
                    <p style="margin:0 0 12px;font-size:20px;font-weight:600;color:#FBFCFF;">Hi {$u} 👋</p>
                    <p style="margin:0 0 20px;font-size:14px;color:rgba(251,252,255,0.8);line-height:1.65;">
                      Thanks for signing up! To activate your account and start exploring the best universities
                      and courses for you, please verify your email address by clicking the button below.
                    </p>

                    <!-- CTA button -->
                    <table cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                      <tr>
                        <td align="center" style="background:#1E5ABC;border-radius:25px;">
                          <a href="{$v}"
                             style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:600;color:#F2EEE9;text-decoration:none;">
                            Verify My Email
                          </a>
                        </td>
                      </tr>
                    </table>

                    <p style="margin:0 0 8px;font-size:12.5px;color:rgba(251,252,255,0.5);line-height:1.6;">
                      This link expires in <strong style="color:rgba(251,252,255,0.7);">24 hours</strong>.
                      If you did not create this account, you can safely ignore this email.
                    </p>
                    <p style="margin:0;font-size:11.5px;color:rgba(251,252,255,0.35);word-break:break-all;">
                        Or copy this link: <a href="{$v}" style="color:#FFFFFF;text-decoration:underline;">{$v}</a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr> 

          <!-- Footer -->
          <tr>
            <td style="padding:0 40px 28px;text-align:center;">
              <p style="margin:0;font-size:11px;color:rgba(251,252,255,0.3);line-height:1.6;">
                © SmartEdu · This is an automated email, please do not reply.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function buildEmailText(string $username, string $verifyUrl): string
{
    return <<<TEXT
Hi {$username},

Thanks for signing up for SmartEdu!

Please verify your email address by visiting the link below:

{$verifyUrl}

This link expires in 24 hours.

If you did not create this account, you can safely ignore this email.

— The SmartEdu Team
TEXT;
}