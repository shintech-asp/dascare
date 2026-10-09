<?php
/**
 * ==================================================
 * EMAIL HELPER — DASCARE
 * --------------------------------------------------
 * Ported from Likhavite's reusables/email_helper.php. Same shape
 * (send_email() + render_email_template()) so the auth endpoints
 * below can call it exactly the way Likhavite's do.
 *
 * NOTE ON THE SMTP ACCOUNT: per your call, this still sends through
 * the Likhavite Gmail account (likhavite@gmail.com) since that's
 * already configured and verified — swap EMAIL_SMTP_DSN /
 * EMAIL_FROM_EMAIL here once DASCARE has its own mailbox. Gmail
 * requires the From address to match the authenticated account (or a
 * verified alias), so EMAIL_FROM_EMAIL is deliberately kept equal to
 * the account in the DSN rather than an @dascare address — a couple
 * of the Likhavite endpoints you sent set `from()` to a *different*
 * address than the DSN login (e.g. no-reply@likhavite.com), which
 * Gmail will typically just silently rewrite to the authenticated
 * address anyway. Not repeating that here.
 *
 * Usage from any endpoint (e.g. /api/auth/register.php):
 *
 *     require __DIR__ . '/../reusables/email_helper.php';
 *
 *     $body = render_email_template(
 *         'Welcome to DASCARE',
 *         "Verify your<br><span style='color:#c0392b;'>email address</span>",
 *         "<p>Hi {$firstName}, use the code below to verify your account.</p>",
 *         'This code expires in 10 minutes.'
 *     );
 *
 *     $result = send_email($email, 'Verify Your Email – DASCARE', $body);
 *
 *     if (!$result['success']) {
 *         // log $result['error']; decide whether the request should still fail
 *     }
 * ================================================== */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// ==================================================
// SMTP CONFIG — Likhavite's Gmail account, reused for DASCARE for now
// ==================================================
const EMAIL_SMTP_DSN   = 'smtp://noriroriro@gmail.com:vleunzavrafvcpzx@smtp.gmail.com:587';
const EMAIL_FROM_EMAIL = 'likhavite@gmail.com';
const EMAIL_FROM_NAME  = 'DASCARE';

/**
 * Send an HTML email.
 *
 * @return array{success: bool, error: ?string}
 */
function send_email(string $to, string $subject, string $htmlBody, ?string $toName = null): array
{
    try {
        $transport = Transport::fromDsn(EMAIL_SMTP_DSN);
        $mailer    = new Mailer($transport);

        $message = (new Email())
            ->from(EMAIL_FROM_NAME . ' <' . EMAIL_FROM_EMAIL . '>')
            ->to($toName ? "{$toName} <{$to}>" : $to)
            ->subject($subject)
            ->html($htmlBody);

        $mailer->send($message);

        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Wrap content in the shared DASCARE branded email shell.
 * Same structure as Likhavite's template (top accent bar, dark
 * header, content card, footer) but re-themed: emergency-response
 * red/navy instead of the artisan terracotta, and a text wordmark
 * since there's no DASCARE logo asset yet (swap in an <img> the same
 * way Likhavite's header does once one exists).
 *
 * @param string      $eyebrow    Small uppercase label above the heading
 * @param string      $heading    Main heading HTML (supports "<br>" + a
 *                                "<span style='color:#c0392b;'>...</span>" wrap)
 * @param string      $bodyHtml   Arbitrary HTML for the main content area
 * @param string|null $footerNote Optional note in the light info box
 *                                (e.g. "This code expires in 10 minutes.").
 *                                Pass null to omit the info box.
 */
function render_email_template(
    string $eyebrow,
    string $heading,
    string $bodyHtml,
    ?string $footerNote = null
): string {
    $infoBox = '';
    if ($footerNote !== null) {
        $infoBox = "
                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='
                      background:#fdecea;
                      border:1px solid #f3c6c1;
                      border-radius:14px;
                    '>
                      <tr>
                        <td style='padding:16px 18px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.7; color:#6b3b38;'>
                          {$footerNote}
                        </td>
                      </tr>
                    </table>
        ";
    }

    $year = date('Y');

    return "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
</head>
<body style='margin:0; padding:0; background-color:#eef1f5;'>

  <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background:#eef1f5; margin:0; padding:28px 0;'>
    <tr>
      <td align='center'>

        <table width='620' cellpadding='0' cellspacing='0' border='0' style='width:620px; max-width:620px; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 14px 40px rgba(15,32,58,0.10); border:1px solid #dfe4ea;'>

          <!-- top accent -->
          <tr>
            <td style='
              height:10px;
              background:
                repeating-linear-gradient(
                  45deg,
                  #c0392b 0px,
                  #c0392b 8px,
                  #a5281b 8px,
                  #a5281b 16px
                );
              line-height:10px;
              font-size:0;
            '>
              &nbsp;
            </td>
          </tr>

          <!-- header -->
          <tr>
            <td style='
              background:
                linear-gradient(135deg, #0f203a 0%, #1c3a63 100%);
              padding:30px 36px 26px 36px;
              text-align:center;
            '>
              <div style='font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:30px; font-weight:700; letter-spacing:1px;'>
                DASCARE
              </div>
              <div style='margin-top:6px; font-family:Arial, Helvetica, sans-serif; color:#c7d3e6; font-size:12px; letter-spacing:2px; text-transform:uppercase;'>
                Disaster Assistance &amp; Emergency Response
              </div>
            </td>
          </tr>

          <!-- content wrap -->
          <tr>
            <td style='padding:0; background-color:#ffffff;'>

              <table width='100%' cellpadding='0' cellspacing='0' border='0'>
                <tr>
                  <td style='padding:38px 40px 20px 40px;'>

                    <!-- eyebrow -->
                    <div style='font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#c0392b; margin-bottom:14px;'>
                      {$eyebrow}
                    </div>

                    <div style='font-family:Arial, Helvetica, sans-serif; font-size:34px; line-height:1.18; font-weight:700; color:#1a1a1a; margin:0 0 14px 0;'>
                      {$heading}
                    </div>

                    <div style='width:120px; height:4px; background:#c0392b; border-radius:999px; margin:0 0 22px 0;'></div>

                    {$bodyHtml}

                    {$infoBox}

                  </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- footer -->
          <tr>
            <td style='
              padding:22px 30px 26px 30px;
              text-align:center;
              background:#f4f6f9;
              border-top:1px solid #e2e7ee;
              font-family:Arial, Helvetica, sans-serif;
              color:#8a94a3;
              font-size:12px;
              line-height:1.7;
            '>
              Keeping communities connected to help when it matters.<br>
              &copy; {$year} DASCARE. All rights reserved.
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
    ";
}

/**
 * Convenience wrapper: builds the OTP card markup used by every
 * verification email (register, resend, login-2FA, forgot-password)
 * so it isn't duplicated across four endpoints the way it was in the
 * Likhavite source.
 */
function render_otp_card(string $otp): string
{
    return "
        <table cellpadding='0' cellspacing='0' border='0' align='center' style='
          margin:0 auto 26px auto;
          border-collapse:separate;
        '>
          <tr>
            <td align='center' style='
              padding:22px 30px;
              border-radius:18px;
              border:1px solid #e2c9c5;
              background-color:#fdf6f5;
              box-shadow:0 10px 24px rgba(192,57,43,0.10);
            '>
              <div style='font-family:Arial, Helvetica, sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#a5281b; margin-bottom:10px; font-weight:700;'>
                Your Verification Code
              </div>
              <div style='
                font-family:\"Courier New\", monospace;
                font-size:36px;
                line-height:1;
                font-weight:700;
                color:#0f203a;
                letter-spacing:10px;
              '>
                {$otp}
              </div>
            </td>
          </tr>
        </table>
    ";
}
