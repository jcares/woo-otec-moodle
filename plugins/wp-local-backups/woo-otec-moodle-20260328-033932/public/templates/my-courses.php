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
        return $value !== '' ? $value : 'Sin fecha';
    }
}
?>
<div class="pcc-my-courses">
    <div class="pcc-my-courses-header">
        <div>
            <h2>Mis cursos</h2>
            <p>Desde aqui puedes entrar directamente a cada curso comprado.</p>
        </div>
    </div>

    <?php if (empty($courses)) : ?>
        <div class="pcc-empty-state">Aun no tienes cursos habilitados. Cuando completes una compra, apareceran aqui automaticamente.</div>
    <?php else : ?>
        <div class="pcc-my-courses-grid">
            <?php foreach ($courses as $course) : ?>
                <article class="pcc-course-card">
                    <div class="pcc-course-card__image-wrap">
                        <img class="pcc-course-card__image" src="<?php echo esc_url((string) $course['image']); ?>" alt="<?php echo esc_attr((string) $course['title']); ?>" loading="lazy">
                        <span class="pcc-course-card__badge">Disponible</span>
                    </div>

                    <div class="pcc-course-card__body">
                        <h3 class="pcc-course-card__title"><?php echo esc_html((string) $course['title']); ?></h3>

                        <ul class="pcc-course-meta-list">
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label">Instructor</span>
                                <span class="pcc-course-meta-value"><?php echo esc_html((string) $course['instructor']); ?></span>
                            </li>
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label">Inicio</span>
                                <span class="pcc-course-meta-value"><?php echo esc_html(pcc_format_course_date($course['start_date'] ?? '')); ?></span>
                            </li>
                            <li class="pcc-course-meta-item">
                                <span class="pcc-course-meta-label">Termino</span>
                                <span class="pcc-course-meta-value"><?php echo esc_html(pcc_format_course_date($course['end_date'] ?? '')); ?></span>
                            </li>
                        </ul>

                        <?php if (!empty($course['access_url'])) : ?>
                            <div class="pcc-course-card__actions">
                                <a class="pcc-course-btn" href="<?php echo esc_url((string) $course['access_url']); ?>">Entrar al curso</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
