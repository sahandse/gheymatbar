<?php
/**
 * Plugin Name: قیمت‌بار
 * Plugin URI: https://github.com/sahandse/gheymatbar
 * Description: نمایش قیمت طلا، سکه، ارز و رمزارز با شورت‌کدهای مجزا، تم‌های قابل تنظیم و رابط فارسی.
 * Version: 1.0.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: gheymatbar
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class Gheymatbar_Plugin {
    const VERSION = '1.0.0';
    const OPTION  = 'gheymatbar_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('gheymatbar_gold', [$this, 'gold_shortcode']);
        add_shortcode('gheymatbar_coin', [$this, 'coin_shortcode']);
        add_shortcode('gheymatbar_currency', [$this, 'currency_shortcode']);
        add_shortcode('gheymatbar_crypto', [$this, 'crypto_shortcode']);
        add_shortcode('gheymatbar_all', [$this, 'all_shortcode']);
    }

    public function defaults() {
        return [
            'layout' => 'horizontal',
            'theme' => 'light',
            'accent' => '#111827',
            'refresh_minutes' => 10,
            'source_note' => 'داده‌ها از منابع عمومی/رایگان دریافت می‌شوند.',
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('gheymatbar_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();
        return [
            'layout' => in_array($in['layout'] ?? '', ['horizontal','vertical'], true) ? $in['layout'] : $d['layout'],
            'theme' => in_array($in['theme'] ?? '', ['light','dark','glass'], true) ? $in['theme'] : $d['theme'],
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'refresh_minutes' => min(60, max(1, absint($in['refresh_minutes'] ?? 10))),
            'source_note' => sanitize_text_field($in['source_note'] ?? $d['source_note']),
        ];
    }

    public function admin_menu() {
        add_menu_page(
            'قیمت‌بار',
            'قیمت‌بار',
            'manage_options',
            'gheymatbar',
            [$this, 'settings_page'],
            'dashicons-chart-line',
            58
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'gheymatbar')) return;
        wp_enqueue_style('gheymatbar-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings();
        ?>
        <div class="wrap gheymatbar-admin">
            <div class="gheymatbar-hero">
                <div>
                    <h1>قیمت‌بار</h1>
                    <p>نمایش قیمت طلا، سکه، ارز و رمزارز با شورت‌کدهای جداگانه.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('gheymatbar_group'); ?>
                <div class="gheymatbar-grid">
                    <section class="gheymatbar-card">
                        <h2>نمایش</h2>
                        <label>چیدمان
                            <select name="<?php echo self::OPTION; ?>[layout]">
                                <option value="horizontal" <?php selected($s['layout'],'horizontal'); ?>>افقی</option>
                                <option value="vertical" <?php selected($s['layout'],'vertical'); ?>>عمودی</option>
                            </select>
                        </label>
                        <label>تم
                            <select name="<?php echo self::OPTION; ?>[theme]">
                                <option value="light" <?php selected($s['theme'],'light'); ?>>روشن</option>
                                <option value="dark" <?php selected($s['theme'],'dark'); ?>>تیره</option>
                                <option value="glass" <?php selected($s['theme'],'glass'); ?>>شیشه‌ای</option>
                            </select>
                        </label>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>داده</h2>
                        <label>بازه بروزرسانی (دقیقه)
                            <input type="number" min="1" max="60" name="<?php echo self::OPTION; ?>[refresh_minutes]" value="<?php echo esc_attr($s['refresh_minutes']); ?>">
                        </label>
                        <label>متن منبع
                            <input type="text" name="<?php echo self::OPTION; ?>[source_note]" value="<?php echo esc_attr($s['source_note']); ?>">
                        </label>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>شورت‌کدها</h2>
                        <code>[gheymatbar_gold]</code>
                        <code>[gheymatbar_coin]</code>
                        <code>[gheymatbar_currency]</code>
                        <code>[gheymatbar_crypto]</code>
                        <code>[gheymatbar_all]</code>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>وضعیت اتصال داده</h2>
                        <p>هسته افزونه، پنل تنظیمات و خروجی شورت‌کدها آماده است. اتصال نهایی به APIهای رایگان در نسخه بعدی همین Repo تکمیل می‌شود.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    private function render_box($title, $items) {
        $s = $this->settings();
        $classes = 'gheymatbar-box gheymatbar-' . esc_attr($s['layout']) . ' gheymatbar-theme-' . esc_attr($s['theme']);
        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" style="--gheymatbar-accent:<?php echo esc_attr($s['accent']); ?>">
            <div class="gheymatbar-title"><?php echo esc_html($title); ?></div>
            <div class="gheymatbar-items">
                <?php foreach ($items as $label => $value): ?>
                    <div class="gheymatbar-item">
                        <span><?php echo esc_html($label); ?></span>
                        <strong><?php echo esc_html($value); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <small><?php echo esc_html($s['source_note']); ?></small>
        </div>
        <style>
            .gheymatbar-box{border:1px solid #e5e7eb;border-radius:18px;padding:16px;margin:12px 0;background:#fff}
            .gheymatbar-title{font-weight:700;margin-bottom:12px}
            .gheymatbar-items{display:flex;gap:10px;flex-wrap:wrap}
            .gheymatbar-vertical .gheymatbar-items{display:grid;grid-template-columns:1fr}
            .gheymatbar-item{border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;min-width:120px}
            .gheymatbar-item span{display:block;font-size:12px;color:#6b7280}
            .gheymatbar-item strong{display:block;margin-top:4px;color:var(--gheymatbar-accent)}
            .gheymatbar-theme-dark{background:#111827;color:#fff;border-color:#1f2937}
            .gheymatbar-theme-dark .gheymatbar-item{border-color:#374151}
            .gheymatbar-theme-glass{background:rgba(255,255,255,.72);backdrop-filter:blur(14px)}
        </style>
        <?php
        return ob_get_clean();
    }

    public function gold_shortcode() {
        return $this->render_box('طلا', [
            'طلای ۱۸ عیار' => '—',
            'طلای ۲۴ عیار' => '—',
        ]);
    }

    public function coin_shortcode() {
        return $this->render_box('سکه', [
            'سکه امامی' => '—',
            'نیم سکه' => '—',
            'ربع سکه' => '—',
        ]);
    }

    public function currency_shortcode() {
        return $this->render_box('ارز', [
            'دلار' => '—',
            'یورو' => '—',
            'درهم' => '—',
        ]);
    }

    public function crypto_shortcode() {
        return $this->render_box('رمزارز', [
            'Bitcoin' => '—',
            'Ethereum' => '—',
            'Tether' => '—',
        ]);
    }

    public function all_shortcode() {
        return $this->gold_shortcode()
            . $this->coin_shortcode()
            . $this->currency_shortcode()
            . $this->crypto_shortcode();
    }
}

new Gheymatbar_Plugin();
