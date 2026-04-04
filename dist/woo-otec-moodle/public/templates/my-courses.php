<?php

/**
 * @var array $courses
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pcc_format_course_date')) {
    function pcc_format_course_date($value): string {
        if (is_numeric($value)) {
            $int = (int) $value;
            if ($int > 0) {
                return wp_date(get_option('date_format'), $int);
            }
        }

        $value = (string) $value;
        return $value !== '' ? $value : __('No date', 'woo-otec-moodle');
    }
}

$portal_title = (string) Woo_OTEC_Moodle_Core::instance()->get_option('portal_title', __('My courses', 'woo-otec-moodle'));
$portal_intro = (string) Woo_OTEC_Moodle_Core::instance()->get_option('portal_intro_text', __('From here you can enter each purchased course directly.', 'woo-otec-moodle'));
$portal_button = (string) Woo_OTEC_Moodle_Core::instance()->get_option('portal_button_text', __('Enter course', 'woo-otec-moodle'));
?>
<div class="pcc-my-courses">
    <div class="pcc-my-courses-header">
        <div>
            <h2><?php echo esc_html($portal_title); ?></h2>
            <p><?php echo esc_html($portal_intro); ?></p>
        </div>
    </div>

    <?php if (empty($courses)) : ?>
        <div class="pcc-empty-state"><?php echo esc_html__('You do not have enabled courses yet. Once you complete a purchase, they will appear here automatically.', 'woo-otec-moodle'); ?></div>
    <?php else : ?>
        <div class="pcc-my-courses-grid">
            <?php foreach ($courses as $course) : ?>
                <article class="pcc-course-card">
                    <div class="pcc-course-card__image-wrap">
                        <img class="pcc-course-card__image" src="<?php echo esc_url((string) $course['image']); ?>" alt="<?php echo esc_attr((string) $course['title']); ?>" loading="lazy">
                        <span class="pcc-course-card__badge"><?php echo esc_html__('Available', 'woo-otec-moodle'); ?></span>
                    </div>

                    <div class="pcc-course-card__body">
                        <h3 class="pcc-course-card__title"><?php echo esc_html((string) $course['title']); ?></h3>

                        <ul class="pcc-course-meta-list">
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label"><?php echo esc_html__('Instructor', 'woo-otec-moodle'); ?></span>
                                <span class="pcc-course-meta-value"><?php echo esc_html((string) $course['instructor']); ?></span>
                            </li>
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label"><?php echo esc_html__('Start', 'woo-otec-moodle'); ?></span>
                                <span class="pcc-course-meta-value"><?php echo esc_html(pcc_format_course_date($course['start_date'] ?? '')); ?></span>
                            </li>
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label"><?php echo esc_html__('End', 'woo-otec-moodle'); ?></span>
                                <span class="pcc-course-meta-value"><?php echo esc_html(pcc_format_course_date($course['end_date'] ?? '')); ?></span>
                            </li>
                        </ul>

                        <?php if (!empty($course['access_url'])) : ?>
                            <div class="pcc-course-card__actions">
                                <a class="pcc-course-btn" href="<?php echo esc_url((string) $course['access_url']); ?>"><?php echo esc_html($portal_button); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
