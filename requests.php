<?php

require('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$PAGE->set_url('/blocks/enrol_demands/requests.php');
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id'=>1), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);

$title = "Inscriptions demandées"; //get_string('pluginname', 'block_enrol_demands');
$PAGE->set_title($title);
$PAGE->set_heading($title);

//$previewnode = $PAGE->navigation->add(get_string('preview'), new moodle_url('/a/link/if/you/want/one.php'), navigation_node::TYPE_CONTAINER);
$previewnode = $PAGE->navigation->add(get_string('sitepages'), navigation_node::TYPE_CONTAINER);
$thingnode = $previewnode->add('Demandes d\'inscription', new moodle_url('/blocks/enrol_demands/requests.php'));
$thingnode->make_active();

$paramnomail = optional_param('nomail', 0, PARAM_INT);
$paramreject = optional_param('reject', 0, PARAM_INT);
$paramenrol = optional_param('enrol', 0, PARAM_INT);
$paramall = optional_param('all', 0, PARAM_INT);
//1 : Accepter tous, 2 : Accepter tous si bonne VET, 3 : Refuser tous, 4 : Refuser tous si mauvaise VET


// NOTIFICATIONS OU PAS
if ($paramnomail) {
    if ($paramnomail == 1) {
	//Ne plus envoyer de notifications.
	$nomailrecord = new stdClass();
	$nomailrecord->userid = $USER->id;
	$DB->insert_record('block_enroldemands_nomail', $nomailrecord);
    } else if ($paramnomail == 2) {
	//Envoyer à nouveau des notifications.
	$DB->delete_records('block_enroldemands_nomail', array('userid' => $USER->id));
    }
}

//REJET D'UNE DEMANDE
if ($paramreject) {
    rejectenroldemand($paramreject);
}

//ACCEPTATION D'UNE DEMANDE
if ($paramenrol) {
    acceptenroldemand($paramenrol);
}

if ($paramall) {
    $sql = "SELECT ae.studentid, ae.courseid, ae.id "
         . "FROM mdl_asked_enrolments ae, mdl_context x, mdl_role_assignments ra "
         . "WHERE ra.userid = $USER->id AND ra.roleid = 3 "
         . "AND ra.contextid = x.id  AND x.contextlevel = 50 AND x.instanceid = ae.courseid "
         . "AND ae.answererid = 0";
    //echo "$sql<br>";

    $askedenrolments = $DB->get_recordset_sql($sql);
    foreach ($askedenrolments as $askedenrolment) {
        switch ($paramall) {
            case 1: //Accepter tous
                acceptenroldemand($askedenrolment->id);
                break;

            case 2: //Accepter tous si bonne VET
                $sql = "SELECT COUNT(sv.id) AS goodpromo "
                     . "FROM mdl_course c, mdl_student_vet sv "
                     . "WHERE c.id = $askedenrolment->courseid "
                     . "AND c.category = sv.categoryid "
                     . "AND sv.studentid = $askedenrolment->studentid";
                $goodpromo = $DB->get_record_sql($sql)->goodpromo;
                if ($goodpromo) {
                    acceptenroldemand($askedenrolment->id);
                }
                break;

            case 3: //Refuser tous
                rejectenroldemand($askedenrolment->id);
                break;

            case 4: //Refuser tous si mauvaise VET
                $sql = "SELECT COUNT(sv.id) AS goodpromo "
                     . "FROM mdl_course c, mdl_student_vet sv "
                     . "WHERE c.id = $askedenrolment->courseid "
                     . "AND c.category = sv.categoryid "
                     . "AND sv.studentid = $askedenrolment->studentid";
                $goodpromo = $DB->get_record_sql($sql)->goodpromo;
                if (!$goodpromo) {
                    rejectenroldemand($askedenrolment->id);
                }
                break;

            default:
                break;
        }
    }
    $askedenrolments->close();
}

echo $OUTPUT->header();


//Si l'utilisateur est un enseignant
$isteacher = $DB->count_records('role_assignments', array('userid' => $USER->id, 'roleid' => 3));

if ($isteacher > 0) {
    ?>
    <br><br>
    <h2>Demandes que vous avez reçues</h2>
    Des étudiants (ou des collègues) vous ont demandé de les inscrire à vos cours :<br><br>
    <a href='requests.php?all=1'><button>Accepter tous</button></a>&nbsp;&nbsp;
    <a href='requests.php?all=2'><button>Accepter tous si bonne VET</button></a><br><br>
    <a href='requests.php?all=3'><button>Refuser tous</button></a>&nbsp;&nbsp;
    <a href='requests.php?all=4'><button>Refuser tous si mauvaise VET</button></a><br><br>
    <br>
    <?php
    if ($DB->record_exists('block_enroldemands_nomail', array('userid' => $USER->id))) {
	echo "<a href='requests.php?nomail=2'><button>Merci de me signaler ces demandes par courriel chaque lundi matin.</button></a>";
    } else {
	echo "<a href='requests.php?nomail=1'><button>Ne plus m'envoyer de courriel pour ces demandes.</button></a>";
    }
    ?>
    <br><br>
<table border-collapse>
    <tr align = 'center' style = 'font-weight:bold;color:#731472' bgcolor='#780D68'>
    	<td>VET du cours</td>
    	<td>Cours</td>
        <td>Demande le</td>
    	<td>Demandeur</td>
        <td>e-mail du demandeur</td>
    	<td>VET(s) du demandeur</td>
    	<td colspan="2">Réponse</td>
    </tr>
    <?php
    $sql = "SELECT ae.studentid, ae.courseid, ae.askedat, ae.id "
         . "FROM mdl_asked_enrolments ae, mdl_context x, mdl_role_assignments ra "
         . "WHERE ra.userid = $USER->id AND ra.roleid = 3 "
         . "AND ra.contextid = x.id  AND x.contextlevel = 50 AND x.instanceid = ae.courseid "
         . "AND ae.answererid = 0";
    //echo "$sql<br>";

    $askedenrolments = $DB->get_recordset_sql($sql);
    foreach ($askedenrolments as $askedenrolment) {
        echo "<tr align = 'center'>";
        $coursesql = "SELECT a.name, a.idnumber, c.fullname, c.idnumber FROM mdl_course c, mdl_course_categories a WHERE c.id = $askedenrolment->courseid AND a.id = c.category";
        $askedenrolmentcourse = $DB->get_record_sql($coursesql);

        echo "<td>$askedenrolmentcourse->name</td>";
        echo "<td><a href='$CFG->wwwroot/course/view.php?id=$askedenrolment->courseid'>($askedenrolmentcourse->idnumber) $askedenrolmentcourse->fullname</td>";
        echo "<td>".date("d/m/Y", $askedenrolment->askedat)."</td>";

        $askersql = "SELECT firstname, lastname, email FROM mdl_user WHERE id = $askedenrolment->studentid";
        $asker = $DB->get_record_sql($askersql);
        echo "<td><a href='$CFG->wwwroot/user/view.php?id=$askedenrolment->studentid'>$asker->firstname $asker->lastname</a></td>";
        echo "<td><a href='mailto:$asker->email'>$asker->email</a>";

        //Le demandeur est-il un enseignant ou un étudiant ?
        $sql = "SELECT COUNT(id) AS isteacher FROM mdl_role_assignments WHERE (roleid = 2 OR roleid = 1) AND userid = $askedenrolment->studentid";
        $askerteacher = $DB->get_record_sql($sql)->isteacher;

        if ($askerteacher) {
            echo "<td style='color:blue'>Enseignant</td>";
        } else {
            //VET(s) dont cet étudiant fait partie
            echo "<td><ul>";
            $sql = "SELECT a.name, a.idnumber FROM mdl_course_categories a, mdl_student_vet s WHERE s.studentid = $askedenrolment->studentid AND s.categoryid = a.id";
            $studentvets = $DB->get_recordset_sql($sql);
            foreach ($studentvets as $studentvet) {
                if ($studentvet->idnumber == $askedenrolmentcourse->idnumber) {
                    echo "<li style='color:green'>$studentvet->name</li>";
                } else {
                    echo "<li style='color:red'>$studentvet->name</li>";
                }
            }
            echo "</ul></td>";
        }

        echo "<td><a href='requests.php?enrol=$askedenrolment->id'>Accepter</a></td>";
        echo "<td><a href='requests.php?reject=$askedenrolment->id'>Refuser</a></td>";
        echo "</tr>";
    }
    ?>
</table>
    <?php
}
?>

<p> </p>
<p> </p>

<h2>Demandes que vous avez déposées</h2>
<a href='<?php echo $CFG->wwwroot; ?>/course/index.php'><button type="button">Ajouter une demande</button></a>
<br><br>
<?php
checkdemands('', 'askedat', 'Demandes en attente');
checkdemands('Oui', 'answeredat', 'Demandes acceptées');
checkdemands('Non', 'answeredat', 'Demandes rejetées');

echo $OUTPUT->footer();

function rejectenroldemand($paramreject) {
    global $DB, $USER;
    $now = time();
    $sql = "UPDATE mdl_asked_enrolments SET answeredat = $now, answer = 'Non', answererid = $USER->id WHERE id = $paramreject";
    $DB->execute($sql);
    //Send mail
    $studentsql = "select studentid, courseid from mdl_asked_enrolments where id = $paramreject";
    $studentres = $DB->get_record_sql($studentsql);
    $coursedata = "select fullname from mdl_course where id =$studentres->courseid";
    $resdatacourse = $DB->get_record_sql($coursedata);
    $studentdata = "select email from mdl_user where id =$studentres->studentid";
    $resstudent = $DB->get_record_sql($studentdata);
    $to = "$resstudent->email";
    $subject = "CoursUCP : Demande d'inscription au cours $resdatacourse->fullname refusée";
    $message = "Bonjour, \n\nVotre demande d'inscription au cours $resdatacourse->fullname vient d'être refusée par $USER->firstname $USER->lastname $USER->email.\n
	Nous vous conseillons : \n
	 1 . De bien vérifier l'intitulé de ce cours : fait-il partie de votre cursus? \n
	 2 . Si tout cela vous semble correct, contacter l'enseignant qui gère le cours.\n\nBien cordialement, \nCoursUCP, votre plateforme pédagogique";
    $headers = 'From: noreply@cours.u-cergy.fr' . "\r\n" .
     'Reply-To: noreply@cours.u-cergy.fr' . "\r\n" .
     'X-Mailer: PHP/' . phpversion();
     mail($to, $subject, $message, $headers);
}

function acceptenroldemand($paramenrol) {
    global $DB, $USER;

    //On vérifie que ce cours appartient bien à cet enseignant
    $sql = "SELECT ae.courseid, x.id as contextid, ae.studentid FROM mdl_asked_enrolments ae, mdl_context x WHERE ae.id = $paramenrol AND x.contextlevel = 50 AND x.instanceid = ae.courseid";
    $acceptedcourse = $DB->get_record_sql($sql);
    $params = array('contextid' => $acceptedcourse->contextid, 'roleid' => 3, 'userid' => $USER->id);
    $iscourseteacher = $DB->get_field('role_assignments', 'id', $params);

    if ($iscourseteacher) {
        //Si cet utilisateur n'est pas encore inscrit à ce cours
        $sql = "SELECT COUNT(ue.id) AS isenroled FROM mdl_enrol e, mdl_user_enrolments ue "
                . "WHERE ue.userid = $acceptedcourse->studentid AND ue.enrolid = e.id AND e.courseid = $acceptedcourse->courseid";

        $isenroled = $DB->get_record_sql($sql)->isenroled;
        if ($isenroled == 0) {
            //on l'y inscrit
            $sql = "SELECT id FROM mdl_enrol WHERE courseid = $acceptedcourse->courseid AND enrol = 'manual'";
            $enrolid = $DB->get_record_sql($sql)->id;
            $DB->insert_record("user_enrolments", array('enrolid'=>$enrolid,'userid'=>$acceptedcourse->studentid,'timestart'=>time(),'timecreated'=>time()));
            //Le demandeur est-il un enseignant ou un étudiant ?
            $sql = "SELECT COUNT(id) AS isteacher FROM mdl_role_assignments WHERE (roleid = 2 OR roleid = 1) AND userid = $acceptedcourse->studentid";
            $askerteacher = $DB->get_record_sql($sql)->isteacher;
            //On lui donne le rôle étudiant ou enseignant, selon ce qu'il est.
            if ($askerteacher) {
                $DB->insert_record("role_assignments", array('roleid'=>3,'contextid'=>$acceptedcourse->contextid,'userid'=>$acceptedcourse->studentid,'timemodified'=>time()));
            } else {
                $DB->insert_record("role_assignments", array('roleid'=>5,'contextid'=>$acceptedcourse->contextid,'userid'=>$acceptedcourse->studentid,'timemodified'=>time()));
            }
        }

        //On note que la demande est acceptée
        $now = time();
        $sql = "UPDATE mdl_asked_enrolments SET answeredat = $now, answer = 'Oui', answererid = $USER->id WHERE id = $paramenrol";
        $DB->execute($sql);

		//Send mail
 	$studentsql = "select studentid, courseid from mdl_asked_enrolments where id = $paramenrol";
	$studentres = $DB->get_record_sql($studentsql);
	$coursedata = "select fullname from mdl_course where id =$studentres->courseid";
	$resdatacourse = $DB->get_record_sql($coursedata);
	$studentdata = "select email from mdl_user where id =$studentres->studentid";
	$resstudent = $DB->get_record_sql($studentdata);
	$to      = "$resstudent->email";
    $subject = "CoursUCP : Demande d'inscription au cours $resdatacourse->fullname acceptée ";
    $message = "Bonjour, \n\nVotre demande d'inscription au cours $resdatacourse->fullname vient d'être acceptée par $USER->firstname $USER->lastname $USER->email.\nVous pouvez y accéder depuis https://cours.u-cergy.fr --> onglet Mes cours.\n\nBon travail !\nCoursUCP, votre plateforme pédagogique";
    $message .= "<br>Ceci est un message automatique. Merci de ne pas y répondre. ";
    $message .= "Pour toute demande ou information, nous vous invitons à <a href='https://monucp.u-cergy.fr/uPortal/f/u312l1s6/p/Assistance.u312l1n252/max/render.uP?pCp'>Effectuer une demande</a> dans la catégorie <strong>SEFIAP -> Applications pédagogiques</strong>.";
    $headers = 'From: noreply@cours.u-cergy.fr' . "\r\n" .'MIME-Version: 1.0' . "\r\n".
               'Reply-To: noreply@cours.u-cergy.fr' . "\r\n" .'Content-type: text/html; charset=utf-8' . "\r\n".
               'X-Mailer: PHP/' . phpversion();
     mail($to, $subject, $message, $headers);
    }
}

function asked_enrolments_table($askedenrolments) {
    global $CFG, $USER, $DB;
    ?>
    <table style='border-collapse'>
        <tr align = 'center' style = 'font-weight:bold;color:#731472' bgcolor='#780D68'>
            <td>VET du cours</td>
            <td>Cours</td>
            <td>Demande le</td>
            <td>État</td>
            <td>Réponse le</td>
            <td>Réponse par</td>
        </tr>
        <?php
        foreach ($askedenrolments as $askedenrolment) {
            echo "<tr align = 'center'>";
			$sqlcourse = "select fullname from mdl_course where id= $askedenrolment->courseid";
			$rescourse =$DB->get_record_sql($sqlcourse);
			if($rescourse->fullname)
			{
            $coursesql = "SELECT a.name, c.fullname, c.idnumber FROM mdl_course c, mdl_course_categories a WHERE c.id = $askedenrolment->courseid AND a.id = c.category";
            $askedenrolmentcourse = $DB->get_record_sql($coursesql);
	    echo "<td>$askedenrolmentcourse->name  no</td>";
            echo "<td><a href='$CFG->wwwroot/course/view.php?id=$askedenrolment->courseid'>($askedenrolmentcourse->idnumber) $askedenrolmentcourse->fullname</td>";
            echo "<td>".date("d/m/Y", $askedenrolment->askedat)."</td>";
            if ($askedenrolment->answererid > 0) {
                if ($askedenrolment->answer == 'Oui') {
                    echo "<td style='color:green'>Inscrit(e)</td>";
                } else {
                    echo "<td style='color:red'>Non Inscrit(e)</td>";
                }
                echo "<td>".date("d/m/Y", $askedenrolment->answeredat)."</td>";
                $answerersql = "SELECT firstname, lastname FROM mdl_user WHERE id = $askedenrolment->answererid";
                $answerer = $DB->get_record_sql($answerersql);
                echo "<td>$answerer->firstname $answerer->lastname</td>";

		/*		//mdl_local_mail_messages
				$record = new stdClass();
				$record->courseid = $askedenrolment->courseid;
				$record->subject = '';
				$record->content = '';
				$record->format = -1;
				$record->draft =1;
				$record->time =time();
				$insertidmsg = $DB->insert_record('local_mail_messages', $record);
				//Drafts
				$record = new stdClass();
				$record->userid         = $USER->id;
				$record->type = 'drafts';
				$record->item = 0;
				$record->messageid = $insertidmsg;
				$record->time = time();
				$record->unread = 0;
				$insertdraft = $DB->insert_record('local_mail_index', $record, false);
				//Course
				$record = new stdClass();
				$record->userid         = $USER->id;
				$record->type = 'course';
				$record->item = $askedenrolment->courseid;
				$record->messageid = $insertidmsg;
				$record->time = time();
				$record->unread = 0;
				$insertcourse = $DB->insert_record('local_mail_index', $record, false);
				//Attachment
				$record = new stdClass();
				$record->userid         = $USER->id;
				$record->type = 'attachment';
				$record->item = 0;
				$record->messageid = $insertidmsg;
				$record->time = time();
				$record->unread = 0;
				$insertattachment = $DB->insert_record('local_mail_index', $record, false);
				if($insertidmsg && $insertdraft && $insertcourse && $insertattachment)
				{
					//From
					$record = new stdClass();
					$record->messageid   = $insertidmsg;
					$record->userid = $USER->id;
					$record->role = 'from';
					$record->unread = 0;
					$record->starred = 0;
					$record->deleted = 0;
					$insertfrom = $DB->insert_record('local_mail_message_users', $record, false);
					//To
					$record = new stdClass();
					$record->messageid   = $insertidmsg;
					$record->userid = $askedenrolment->answererid;
					$record->role = 'to';
					$record->unread = 1;
					$record->starred = 0;
					$record->deleted = 0;
					$insertto = $DB->insert_record('local_mail_message_users', $record, false);
					if($insertfrom && $insertto && $insertidmsg)
					{
						echo "<td><a href='$CFG->wwwroot/local/mail/compose.php?m=$insertidmsg'> $answerer->firstname $answerer->lastname <img src ='$CFG->wwwroot/blocks/enrol_demands/pix/envelope.png' height='20' width='30'/></a></td>";
					}
				}*/
			} else {
                echo "<td> En attente </td><td> - </td><td> - </td>";
            }
            echo "</tr>";
		}
        }
        ?>
    </table>
    <br>
<?php
}

function checkdemands($answer, $orderby, $label) {
    global $DB, $USER;
    $sql = "SELECT id, courseid, askedat, answer, answeredat, answererid "
            . "FROM mdl_asked_enrolments "
            . "WHERE studentid = $USER->id "
            . "AND answer = '$answer' "
            . "ORDER BY $orderby DESC";
    $demands = $DB->get_records_sql($sql);
    if ($demands) {
        echo "<h3>$label</h3>";
        asked_enrolments_table($demands);
    }
}


