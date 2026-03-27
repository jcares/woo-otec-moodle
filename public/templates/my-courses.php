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
<div class="pcc-my-courses container my-4">
    <?php if (empty($courses)) : ?>
        <div class="alert alert-info mb-0">No tienes cursos disponibles todavía.</div>
    <?php else : ?>
        <div class="row g-4">
            <?php foreach ($courses as $course) : ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="card h-100 shadow-sm pcc-course-card">
                        <img class="card-img-top pcc-course-card__image" src="<?php echo esc_url((string) $course['image']); ?>" alt="<?php echo esc_attr((string) $course['title']); ?>" loading="lazy">
                        <div class="card-body d-flex flex-column">
                            <h3 class="h5 card-title mb-2"><?php echo esc_html((string) $course['title']); ?></h3>
                            <p class="mb-1"><strong>Instructor:</strong> <?php echo esc_html((string) $course['instructor']); ?></p>
                            <p class="mb-1"><strong>Inicio:</strong> <?php echo esc_html(pcc_format_course_date($course['start_date'] ?? '')); ?></p>
                            <p class="mb-3"><strong>Termino:</strong> <?php echo esc_html(pcc_format_course_date($course['end_date'] ?? '')); ?></p>
                            <?php if (!empty($course['access_url'])) : ?>
                                <a class="btn btn-primary mt-auto" href="<?php echo esc_url((string) $course['access_url']); ?>">Acceder al curso</a>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
