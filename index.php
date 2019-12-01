<?php

include_once 'db_connect.php';

function getCourseIdByName($conn, $courseName) {
    // Gets course id from course name
    $sql = "SELECT id FROM courses WHERE name=:course";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['course' => $courseName]);
    $result = $stmt->fetchAll()[0];

    return $result['id'];
}

function getStudentIdByName($conn, $firstLast) {
	if (count($firstLast) > 2) {
		$i = 0;
		foreach ($firstLast as $name) {
			if ($i > 1) {
				$firstLast[1] = $firstLast[1]  . ' '. $name;
			}
			$i++;
		}
	}

    $sql = "SELECT id FROM students WHERE firstname=:studentFirst AND lastname=:studentLast";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['studentFirst' => $firstLast[0], 'studentLast' => $firstLast[1]]);
    $result = $stmt->fetch();

     return $result[0];
}

function getStudentNameById($conn, $studentId) {
    $sql = "SELECT * FROM students WHERE id=:studentId";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['studentId' => $studentId]);
    $result = $stmt->fetch();

    return $result[1] . ' ' . $result[2];
}

function getCourseNameById($conn, $courseId) {
    // Gets course name from course id
    $sql = "SELECT name FROM courses WHERE id=:coursesId";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['coursesId' => $courseId]);
    return $stmt->fetch();
}

function fetchStudents($conn) {
	$students = array();

	$sql = 'SELECT id FROM students';
	$stmt = $conn->query($sql);
	$result=$stmt->fetchAll();

	foreach ($result as $studentDatum) {
		array_push($students, getStudentNameById($conn, $studentDatum['id']));
	}

	return $students;
}

function fetchStudentNamesAndCourses($conn) {
    $sql = "SELECT * FROM studcourse";
    $stmt = $conn->query($sql);

    $students = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Adds name(s) to students
        array_push($students, array('name' => getStudentNameById($conn, $row['studentsid'])));



        // Adds course name to students
        array_push($students, array('course' => getCourseNameById($conn, $row['coursesid'])));
    }

    return $students;
}

function fetchCourseStudents($conn, $courseId) {
	$textNames = array();

	$sql = 'SELECT * FROM studcourse WHERE coursesid=:coursesId';
	$stmt = $conn->prepare($sql);
	$stmt->execute(['coursesId' => $courseId]);

	foreach ($stmt->fetchAll() as $studentRaw) {
		array_push($textNames, getStudentNameById($conn, $studentRaw['studentsid']));
	}

	return $textNames;
}

function fetchCourses($conn) {
    $courses = array();
	$sql = "SELECT * FROM courses";
	$stmt = $conn->query($sql);

	foreach ($stmt->fetchAll() as $course) {
		array_push($courses, $course['name']);
	}

    return $courses;
}

function fetchAllCourseStudents($conn) {
	$final = array();
	foreach (fetchCourses($conn) as $course) {
		$tmp = fetchCourseStudents($conn, getCourseIdByName($conn, $course));
		array_push($final, array($course => $tmp));
	}

	return $final;
}

function register($conn) {

    $course_id = getCourseIdByName($conn, $_POST['course']);
    $student_id = getStudentIdByName($conn, explode(' ', $_POST['student']));

    // Checks if user is already enrolled in the course
    if (!in_array($_POST['student'], fetchCourseStudents($conn, $course_id))) {
	    $sql = "INSERT INTO studcourse(coursesid, studentsid) VALUES(:coursesid, :studentsid)";
	    $result = $conn->prepare($sql);
	    $res = $result->execute([':coursesid' => $course_id, ':studentsid' => $student_id]);
	}
}

function delete($conn, $studentId, $courseId) {
    $sql = "DELETE FROM studcourse WHERE studentsid=:studentId AND coursesid=:courseId";
    $res = $conn->prepare($sql)->execute([
    		'studentId' => $studentId,
	        'courseId' => $courseId
    ]);
}

if (isset($_POST['course'])) {
    register($conn);
    header("Location: " . $_SERVER['PHP_SELF']);
}

if (isset($_GET['action'])) {
	if ($_GET['action'] == 'delete') {
        delete($conn, $_GET['studentsid'], $_GET['coursesid']);
        header("Location: " . $_SERVER['PHP_SELF']);
    }
}

$students = fetchStudents($conn);
$courses = fetchCourses($conn);
$courseStudents = fetchAllCourseStudents($conn)[0];
?>

<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title>Registreringssida</title>

		<link rel="stylesheet" href="style/style.css" />
		<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.0/themes/base/jquery-ui.css" />
		<link rel="stylesheet" href="style/query-ui.css" />

		<script src="http://code.jquery.com/jquery-1.8.3.js"></script>
		<script src="http://code.jquery.com/ui/1.10.0/jquery-ui.js"></script>

	</head>
	<body>
		<div id="container">
			<h1>Registrering</h1>
			<form id="register_form" method="post" action="index.php">
				<fieldset>
					<p>
						<label for="student">Välj elev</label>
						<input type="text" name="student" list="students" placeholder="Studerande" required>
						<datalist id="students">
							<?php foreach($students as $student) {?>
								<option value="<?php echo $student?>">
							<?php }?>
						</datalist>

						<label for="course">Välj kurs</label>
						<input type="text" name="course" list="courses" placeholder="Kurs" required>
						<datalist id="courses">
                            <?php foreach($courses as $course) {?>
								<option value="<?php echo $course?>">
                            <?php }?>
						</datalist>

						<input type="submit" name="register" class="btn" id="register" value="Registrera!">
					</p>
				</fieldset>
			</form>
		</div>

		<div id="lists">
            <?php foreach ($courses as $course) {?>
				<ul class="course_list">
					<li class="head"><?php echo $course?></li>
					<?php foreach (fetchCourseStudents($conn, getCourseIdByName($conn, $course)) as $student) { ?>
						<li><?php echo $student?>
							<a href="?action=delete&studentsid=<?php echo getStudentIdByName($conn, explode(' ', $student))?>&coursesid=<?php echo getCourseIdByName($conn, $course)?>">Radera</a>
						</li>
					<?php } ?>
				</ul>
            <?php }?>
		</div>
	</body>
</html>