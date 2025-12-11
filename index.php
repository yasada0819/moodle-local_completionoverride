<?php
require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/completionoverride:manage', $context);

$course = get_course($courseid);
$completion = new completion_info($course);
$modinfo = get_fast_modinfo($course);

// 🔄 ステータス更新処理
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

if (($action === 'complete' || $action === 'incomplete') && $userid && $cmid) {
    require_sesskey();
    global $DB, $USER;

    $cm = get_coursemodule_from_id(null, $cmid, $courseid, false, MUST_EXIST);
    $params = ['userid' => $userid, 'coursemoduleid' => $cm->id];
    $record = $DB->get_record('course_modules_completion', $params);
    $newstate = $action === 'complete' ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

    if ($record) {
        $record->completionstate = $newstate;
        $record->timemodified = time();
        $record->overrideby = $USER->id;
        $DB->update_record('course_modules_completion', $record);
    } else {
        $record = (object)[
            'coursemoduleid' => $cm->id,
            'userid' => $userid,
            'completionstate' => $newstate,
            'viewed' => 1,
            'timemodified' => time(),
            'overrideby' => $USER->id
        ];
        $DB->insert_record('course_modules_completion', $record);
    }

    redirect(new moodle_url('/local/completionoverride/index.php', [
        'courseid' => $courseid,
        'search' => $search
    ]));
}

$PAGE->requires->js('/local/completionoverride/completion.js'); // JS読み込み

echo $OUTPUT->header();
echo $OUTPUT->heading("活動完了ステータスの上書き");

echo '<form method="get" action="">
    <input type="hidden" name="courseid" value="' . $courseid . '">
    <input type="text" name="search" placeholder="氏名 または ID番号で検索" value="' . s($search) . '" style="padding:6px; width:300px;">
    <button type="submit" style="padding:6px;">検索</button>
</form><br>';

if (!empty($search)) {
    $users = get_enrolled_users($context);
    $matchedusers = [];

    foreach ($users as $user) {
        $name = fullname($user);
        if (stripos($name, $search) !== false || stripos($user->idnumber, $search) !== false) {
            $matchedusers[] = $user;
        }
    }

    if (empty($matchedusers)) {
        echo '<p>該当するユーザが見つかりませんでした。</p>';
    } else {
        echo '<table class="generaltable"><thead><tr><th>ユーザ<br>(username / idnumber)</th>';
        foreach ($modinfo->cms as $cm) {
            if ($completion->is_enabled($cm)) {
                echo '<th>' . format_string($cm->name) . '</th>';
            }
        }
        echo '</tr></thead><tbody>';

        foreach ($matchedusers as $user) {
            echo '<tr>';
            echo '<td>' . fullname($user) . "<br><span style='color:gray;'>($user->username / $user->idnumber)</span></td>";

            foreach ($modinfo->cms as $cm) {
                if ($completion->is_enabled($cm)) {
                    $status = $completion->get_data($cm, false, $user->id);
                    $toggleAction = $status->completionstate == COMPLETION_COMPLETE ? 'incomplete' : 'complete';

                    $record = $DB->get_record('course_modules_completion', [
                        'userid' => $user->id,
                        'coursemoduleid' => $cm->id
                    ]);

                    $prefix = ($cm->completion == COMPLETION_TRACKING_MANUAL) ? 'manual' : 'auto';
                    $suffix = ($status->completionstate == COMPLETION_COMPLETE) ? 'y' : 'n';
                    $override = (!empty($record) && !empty($record->overrideby)) ? '-override' : '';
                    $iconname = "i/completion-{$prefix}-{$suffix}{$override}";
                    $iconurl = $OUTPUT->image_url($iconname);
                    $icon = html_writer::empty_tag('img', ['src' => $iconurl, 'alt' => '完了']);

                    $currenttext = ($suffix === 'y' ? '完了' : '未完了') . ($override ? '（上書き）' : '（本人）');
                    $nexttext = ($toggleAction === 'complete' ? '完了' : '未完了') . '（上書き）';

                    $url = new moodle_url('/local/completionoverride/index.php', [
                        'courseid' => $courseid,
                        'userid' => $user->id,
                        'cmid' => $cm->id,
                        'action' => $toggleAction,
                        'sesskey' => sesskey(),
                        'search' => $search
                    ]);

                    echo '<td style="text-align:center">';
                    echo html_writer::link($url, $icon, [
                        'class' => 'completion-toggle',
                        'data-current' => s($currenttext),
                        'data-next' => s($nexttext),
                        'data-username' => s(fullname($user) . "（{$user->idnumber}）"),
                        'data-activity' => s(format_string($cm->name))
                    ]);
                    echo '</td>';
                }
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}

echo $OUTPUT->footer();