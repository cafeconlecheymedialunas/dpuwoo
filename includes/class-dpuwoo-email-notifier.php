<?php
if (!defined('ABSPATH')) exit;

class Email_Notifier
{
    private string $to;
    private string $subject;
    private string $message;
    private array $headers;

    public function __construct()
    {
        $opts = get_option('dpuwoo_settings', []);
        $this->to = $opts['cron_notify_email'] ?? get_option('admin_email');
        $this->headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];
    }

    public function send_simulation_results(array $results, string $context = 'cron'): bool
    {
        $this->subject = $this->build_subject($results, $context);
        $this->message = $this->build_simulation_email($results, $context);
        
        return wp_mail($this->to, $this->subject, $this->message, $this->headers);
    }

    public function send_update_results(array $results, string $context = 'cron'): bool
    {
        $this->subject = $this->build_subject($results, $context);
        $this->message = $this->build_update_email($results, $context);
        
        return wp_mail($this->to, $this->subject, $this->message, $this->headers);
    }

    private function build_subject(array $results, string $context): string
    {
        $action = $results['simulate'] ?? false ? 'Simulación' : 'Actualización';
        $pct_change = $results['percentage_change'] ?? 0;
        $direction = $pct_change >= 0 ? '↑' : '↓';
        
        return sprintf(
            '[%s] Dollar Sync - %s completada (%s%.2f%%)',
            get_bloginfo('name'),
            $action,
            $direction,
            abs($pct_change)
        );
    }

    private function build_simulation_email(array $results, string $context): string
    {
        $pct_change = $results['percentage_change'] ?? 0;
        $prev_rate = $results['previous_rate'] ?? 0;
        $new_rate = $results['rate'] ?? 0;
        $updated = $results['summary']['updated'] ?? 0;
        $skipped = $results['summary']['skipped'] ?? 0;
        $errors = $results['summary']['errors'] ?? 0;
        $total = $updated + $skipped;

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dollar Sync - Simulación</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background: #f6f6f6; }
        .container { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eee; }
        .header h1 { margin: 0; font-size: 20px; color: #1a1a1a; }
        .header p { margin: 8px 0 0; color: #666; font-size: 14px; }
        .warning { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 16px; margin-bottom: 20px; text-align: center; }
        .warning p { margin: 0; color: #c2410c; font-size: 14px; }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .stat { background: #f9fafb; border-radius: 6px; padding: 16px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #1a1a1a; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-top: 4px; }
        .stat.up .stat-value { color: #16a34a; }
        .stat.down .stat-value { color: #dc2626; }
        .rate-box { background: linear-gradient(135deg, #eef2ff 0%, #f0fdf4 100%); border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px; }
        .rate-change { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .rate-change.up { color: #16a34a; }
        .rate-change.down { color: #dc2626; }
        .rate-change.neutral { color: #666; }
        .rate-values { font-size: 14px; color: #666; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Dollar Sync - Simulación Cron</h1>
            <p>Esta fue una simulación. Precios no modificados.</p>
        </div>

        <div class="warning">
            <p><strong>⚠️ Simulación</strong> — No se realizaron cambios en los precios.</p>
        </div>

        <div class="rate-box">
            <div class="rate-change <?php echo $pct_change > 0 ? 'up' : ($pct_change < 0 ? 'down' : 'neutral'); ?>">
                <?php echo $pct_change > 0 ? '+' : ''; ?><?php echo number_format($pct_change, 2); ?>%
            </div>
            <div class="rate-values">
                <?php echo $prev_rate > 0 ? '$' . number_format($prev_rate, 2) : '???'; ?> → $<?php echo number_format($new_rate, 2); ?>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?php echo number_format($updated); ?></div>
                <div class="stat-label">Productos afectados</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo number_format($total); ?></div>
                <div class="stat-label">Total procesado</div>
            </div>
        </div>

        <?php if ($errors > 0): ?>
        <div class="stat" style="background: #fef2f2;">
            <div class="stat-value" style="color: #dc2626;"><?php echo number_format($errors); ?></div>
            <div class="stat-label">Errores</div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Generado automáticamente por Dollar Sync</p>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    private function build_update_email(array $results, string $context): string
    {
        $pct_change = $results['percentage_change'] ?? 0;
        $prev_rate = $results['previous_rate'] ?? 0;
        $new_rate = $results['rate'] ?? 0;
        $updated = $results['summary']['updated'] ?? 0;
        $skipped = $results['summary']['skipped'] ?? 0;
        $errors = $results['summary']['errors'] ?? 0;
        $total = $updated + $skipped;

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dollar Sync - Actualización</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background: #f6f6f6; }
        .container { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eee; }
        .header h1 { margin: 0; font-size: 20px; color: #1a1a1a; }
        .header p { margin: 8px 0 0; color: #666; font-size: 14px; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 16px; margin-bottom: 20px; text-align: center; }
        .success p { margin: 0; color: #166534; font-size: 14px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat { background: #f9fafb; border-radius: 6px; padding: 16px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #1a1a1a; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-top: 4px; }
        .stat.updated .stat-value { color: #16a34a; }
        .stat.skipped .stat-value { color: #666; }
        .stat.errors .stat-value { color: #dc2626; }
        .rate-box { background: linear-gradient(135deg, #eef2ff 0%, #f0fdf4 100%); border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px; }
        .rate-change { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .rate-change.up { color: #16a34a; }
        .rate-change.down { color: #dc2626; }
        .rate-change.neutral { color: #666; }
        .rate-values { font-size: 14px; color: #666; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Dollar Sync - Actualización Completada</h1>
            <p>Los precios de tus productos fueron actualizados.</p>
        </div>

        <div class="success">
            <p><strong>✓ Actualización exitosa</strong> — Los precios fueron modificados.</p>
        </div>

        <div class="rate-box">
            <div class="rate-change <?php echo $pct_change > 0 ? 'up' : ($pct_change < 0 ? 'down' : 'neutral'); ?>">
                <?php echo $pct_change > 0 ? '+' : ''; ?><?php echo number_format($pct_change, 2); ?>%
            </div>
            <div class="rate-values">
                <?php echo $prev_rate > 0 ? '$' . number_format($prev_rate, 2) : '???'; ?> → $<?php echo number_format($new_rate, 2); ?>
            </div>
        </div>

        <div class="stats">
            <div class="stat updated">
                <div class="stat-value"><?php echo number_format($updated); ?></div>
                <div class="stat-label">Actualizados</div>
            </div>
            <div class="stat skipped">
                <div class="stat-value"><?php echo number_format($skipped); ?></div>
                <div class="stat-label">Sin cambios</div>
            </div>
            <div class="stat errors">
                <div class="stat-value"><?php echo number_format($errors); ?></div>
                <div class="stat-label">Errores</div>
            </div>
        </div>

        <div class="footer">
            <p>Generado automáticamente por Dollar Sync</p>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
