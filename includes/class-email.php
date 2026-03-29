<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_Email {

    /**
     * Send magic link invitation email to a client
     */
    public function send_invite($member_email, $member_name, $board_title, $magic_link, $is_resend = false) {
        $subject = $is_resend
            ? "Your updated access link for {$board_title}"
            : "You've been invited to {$board_title}";

        $body = $this->get_email_template($member_name, $board_title, $magic_link, $is_resend);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Charleston Website Design Studio <noreply@charlestonwebsitestudio.com>'
        );

        return wp_mail($member_email, $subject, $body, $headers);
    }

    /**
     * Build branded email HTML
     */
    private function get_email_template($name, $board_title, $magic_link, $is_resend) {
        $greeting = $is_resend ? "Here's your updated access link" : "You've been invited to collaborate";

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;600;700&display=swap');
            </style>
        </head>
        <body style="margin: 0; padding: 0; background-color: #000000; font-family: 'Unbounded', Arial, sans-serif;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #000000;">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px;">
                            <!-- Header -->
                            <tr>
                                <td style="padding: 30px 40px; border-bottom: 2px solid #E3FF04;">
                                    <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #E3FF04; letter-spacing: 1px;">
                                        CWDS
                                    </h1>
                                    <p style="margin: 4px 0 0; font-size: 10px; color: #666; letter-spacing: 2px; text-transform: uppercase;">
                                        Charleston Website Design Studio
                                    </p>
                                </td>
                            </tr>
                            <!-- Body -->
                            <tr>
                                <td style="padding: 40px;">
                                    <p style="margin: 0 0 8px; font-size: 12px; color: #E3FF04; letter-spacing: 2px; text-transform: uppercase; font-weight: 600;">
                                        Project Board Access
                                    </p>
                                    <h2 style="margin: 0 0 24px; font-size: 22px; color: #ffffff; font-weight: 600; line-height: 1.3;">
                                        <?php echo esc_html($greeting); ?>
                                    </h2>
                                    <p style="margin: 0 0 8px; font-size: 14px; color: #cccccc; line-height: 1.6;">
                                        Hi <?php echo esc_html($name); ?>,
                                    </p>
                                    <p style="margin: 0 0 30px; font-size: 14px; color: #cccccc; line-height: 1.6;">
                                        Your project board <strong style="color: #ffffff;">"<?php echo esc_html($board_title); ?>"</strong> is ready. Click below to access your board — no password needed.
                                    </p>
                                    <!-- CTA Button -->
                                    <table role="presentation" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="background-color: #E3FF04; border-radius: 4px;">
                                                <a href="<?php echo esc_url($magic_link); ?>" style="display: inline-block; padding: 14px 32px; font-family: 'Unbounded', Arial, sans-serif; font-size: 13px; font-weight: 700; color: #000000; text-decoration: none; letter-spacing: 1px; text-transform: uppercase;">
                                                    Open My Board →
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin: 24px 0 0; font-size: 11px; color: #666; line-height: 1.5;">
                                        This link expires in 48 hours. If it expires, contact us for a new one.
                                    </p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 24px 40px; border-top: 1px solid #222;">
                                    <p style="margin: 0; font-size: 11px; color: #444; line-height: 1.5;">
                                        Charleston Website Design Studio<br>
                                        <a href="https://charlestonwebsitestudio.com" style="color: #E3FF04; text-decoration: none;">charlestonwebsitestudio.com</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
