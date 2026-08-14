<?php
/**
 * Theme footer template.
 *
 * @package TailPress
 */
?>
        </main>

        <?php do_action('tailpress_content_end'); ?>
    </div>

    <?php do_action('tailpress_content_after'); ?>

    <footer id="colophon" class="storefront-footer" role="contentinfo">
        <div class="wrap">
            <?php do_action('tailpress_footer'); ?>
            <span class="eyebrow">&copy; <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?></span>
            <span class="eyebrow"><?php esc_html_e('Free shipping over $100 · 30-day returns', 'tailpress'); ?></span>
        </div>
    </footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
