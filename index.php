<?php 
/**
 * ProComm2: Pronounciation Learning Tool
 * University of British Columbia Arts ISIT
 * 
 * @author Navid Fattahi <navid.fattahi(at)alumni.ubc.ca>
 * @author Thomas Dang   <thomas.dang(at)ubc.ca>
 *
 * @version 0.1
 * 
 **/
 
	$translateLessonDesc = null;
	if (isset($_GET['translate'])) {
		$translateLessonDesc = $_GET['translate'];
	}
	
	include 'getLessonFiles.php';
	//session_start();
	$lessonsJSON = $_SESSION['lessons_array'];
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Pronunciation Learning Tool</title>	

	<!-- WAMI Recorder Files -->
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js"></script>
	<script type="text/javascript" src="recorder.js"></script>
	<script type="text/javascript" src="gui.js"></script>
	<!-- MediaElement Library Files -->
	<script src="mediaelement/build/jquery.js"></script>
	<script src="mediaelement/build/mediaelement-and-player.min.js"></script>
	<script src="js/jquery.ui.core.js"></script>
	<script src="js/jquery.ui.widget.js"></script>
	<script src="js/jquery.ui.progressbar.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.bsAlerts.min.js"></script>
	<link rel="stylesheet" href="mediaelement/build/mediaelementplayer.min.css" />
	<link rel="stylesheet" href="css/jquery.ui.progressbar.css" />
	<link rel="stylesheet" href="css/bootstrap.min.css" />
	<link rel="stylesheet" href="css/style.css" />
	<script>
	
	/*** Global Variables ***/
	var lessonsJSON = <?php echo json_encode($lessonsJSON); ?>;
	var baseurl = lessonsJSON[0];
	var lessonstatus = 1; 	// current status - 0: chapter just initiated, 1-n: current part number
	var numparts = 0;
	var numchaps = 0;
	var chapnum = 0;
	var partnum = 0;
	var exernum = 0;
	var globalXML;
	var newChapterSelected = 0;
	var recorderProgress = 0;
	var recorderTimer;
	var isPaused = false;
	var firstRun = true;

	var autoDetectRecordingTime = true;
	var DEFAULT_RECORDING_TIME_PER_CHAR = 0.167;
	var recordingTime = 2.0; // seconds
	var isReplaying = false;
	
	var defaultLessonDescText = "Repeat the text here after the instructor's recording";
	
	var WAMI_TEST_URL = "https://wami-recorder.appspot.com/audio";
	var recordingURL = "uploads/record.php";
	var playURL =	"uploads/files/";
	
	var RAND_MAX = 10000000;
	var lastRecordingName = "";
	
	var uploadPath = null;
	var playPath = null;

	// Initiate the WAMI recorder
	var WamiGUI;
	var isWamiReady = false;

	function setupRecorder(callBackFunction) 
	{
		if (isWamiReady) {
			callBackFunction();
		} else {
			isWamiReady = true;
			Wami.setup({
				id : "wami"
				/*, onReady : setupGUI*/
				, onReady : callBackFunction
			});
		}
	}
	
	// UNUSED - DEBUG ONLY!
	function setupGUI() 
	{
		WamiGUI = new Wami.GUI({
			id : "wami",
			recordUrl : "https://wami-recorder.appspot.com/audio",
			playUrl : "https://wami-recorder.appspot.com/audio"
		});

		WamiGUI.setPlayEnabled(false);
	}	
	
	function estimateRecordingTime(str) {
		var recTime = str.length * 1.0 * DEFAULT_RECORDING_TIME_PER_CHAR;

		if (recTime < 2) recTime = Math.round(recTime);
		recTime = (recTime < 1.6) ? 1.6 : recTime;
		recTime = (recTime > 4.0) ? 4.0 : recTime;
		return recTime;
	}
	
	function generateUploadPath() {		 
		if (uploadPath === null) {
			lastRecordingName = Math.ceil(Math.random() * RAND_MAX) + ".wav";
			uploadPath = recordingURL + "?name=" + "files/" + lastRecordingName;
		}
		
		return uploadPath;
	}
	
	function generatePlayPath() {
		if (playPath === null) {
			playPath = playURL + lastRecordingName;
		}
		
		return playPath;
	}

	function estimateCommunicationLag(recTime) {
		var lag = ((recTime * 1000)  / 3);
		lag = (lag < 700) ? (lag + 100) : lag;
		return lag;
	}
	
	function initiateRecordingTime() {
		var tempRecTime = parseFloat($("#extra_recording_time_select").val());
		if (!isNaN(tempRecTime)) {
			recordingTime = tempRecTime;
			autoDetectRecordingTime = false;
		} else {
			autoDetectRecordingTime = true;
		}
	}

	function getRecordingTime() {
		var recTime = recordingTime;
		if (autoDetectRecordingTime) {
			recTime = estimateRecordingTime($('#lesson-desc').html()); 
		} 
		return recTime;
	}
	
	// ENTRY POINT!
	$(document).ready(function () 
	{		
		initiateRecordingTime();
		$("#extra_recording_time_select").change(initiateRecordingTime);
		
		initializeAllChapters();
	});
	
	function initializeAllChapters () 
	{
		// Count number of chapters
		for(var key in lessonsJSON)
			numchaps++;

		for ( var i = 1; i < numchaps; i++ )
			$("#chapter select").append('<option value="part' + i + '">' + i + '</option>');		

		chapnum = 0;
		exernum = 0;
		newChapterSelected = 1;
		getChapterXML(chapnum, exernum);	
	}
	
	function getChapterXML(chapterindex, exerciseindex) 
	{
		chaptemp = chapterindex + 1;
		var str = "";
		
		if (chapterindex < 9)
		{
			chaptemp = "0" + chaptemp;
		}

		if (newChapterSelected == 1)
		{
			getExerciseFiles(chapterindex + 1);
			newChapterSelected = 0;
		}		
		
		str = baseurl + '/ch' + chaptemp + "/" + $("#exercise select option").eq(exerciseindex).val();
		$.ajax({
			type: "GET",
			url: str,
			dataType: "xml",
			success: xmlParser,
			error: function(request, status, error) { 
				alertMessage ("lesson files could not be loaded.", "error"); 
			}
		});
	}
	
	function getExerciseFiles(chapter)
	{
		$("#exercise select").html("");
		var filepath = baseurl;
		if (chapter < 10)
			filepath = filepath + '/ch0' + chapter + '/';
		else
			filepath = filepath + '/ch'  + chapter + '/';
		for(var j in lessonsJSON[chapter])
			getExercises(filepath, lessonsJSON[chapter][j], parseInt(j)+1)
	}
	
	function getExercises(filepath, filename, exerindex)
	{
		$.ajax({
			type: "GET",
			url: filepath + filename,
			async: false,
			dataType: "xml",
			success: function(xml) {
			   getExerciseTitle(xml, filename, exerindex);
			},
			error: function(request, status, error) { 
				alertMessage ("lesson files could not be loaded.", "error"); 
			}
		});
	}
	
	function getExerciseTitle(xml,exercisefilename, exerNumber)
	{
		$("#exercise select").append('<option value="' + exercisefilename + '">' + exerNumber + " - " + $(xml).find('title').text() + '</option>');
	}	
		
	function xmlParser(xml) 
	{
		globalXML = xml;
		$('#load').fadeOut();
		
		$("#part select").html("");
		
		// Populate the instruction box for the current exercise
		$(xml).find('[type="Introduction"]').each(function () {
			$("#instructions-box").html( $(this).find('[name="textIntroduction"]').text().replace(/\n{1,}/g, '<br/>'));
		});
		
		// Populate exercise part list menu
		$(xml).find('[type="Sound Element"]').each(function (index) {
			partnum = index + 1;
			$("#part select").append('<option value="part' + partnum + '">' + partnum + '</option>');
		});
		numparts = partnum;

		var soundpath = baseurl;
		if (chapnum < 9) 
			soundpath = soundpath + '/ch0' + parseInt(chapnum + 1) + '/';
		else
			soundpath = soundpath + '/ch' + parseInt(chapnum + 1) + '/';
			
		// Load the initial instruction sound file
		$(xml).find('[type="Introduction"]').is(function () {
			$("#player2").attr("src", soundpath + $(this).find('[name="soundIntroduction"]').text());
		});
		
		// LESSON BEGINS
		setupRecorder(beginLesson);
	}

	function beginLesson() 
	{	
		$('#lesson-desc').html(defaultLessonDescText);
		$('#translated-lesson-desc').html("");
		
		lessonstatus = 0;
		recorderProgress = 0;
		clearInterval(recorderTimer);
		$(function () {
			$("#progressbar").progressbar({
				value: recorderProgress
			});
		});

		if (firstRun) {
			firstRun = false;
			  $(document).trigger("add-alerts", [
				{
				  'message': "1) Listen to the instructor's recording",
				  'priority': 'warning'
				},
				{
				  'message': "2) Record your own voice",
				  'priority': 'warning'
				},
				{
				  'message': "3) The instructor's recording is replayed",
				  'priority': 'warning'
				},
				{
				  'message': "4) Your own voice is replayed for your comparison",
				  'priority': 'warning'
				},
				{
				  'message': "&nbsp;",
				  'priority': 'warning'
				},
				{
				  'message': "The tool automatically goes through these steps for each sample then proceed to the next. You can repeat the current sample at any point by pressing 'pause' and then 'resume'\n",
				  'priority': 'warning'
				}
			  ]);
		}  

		player.play();
	}	
	
	function onButtonClick(buttonindex)
	{
		if (buttonindex == 3)
			lessonstatus = 1;

		var soundpath = baseurl;
		if (chapnum < 9)
			soundpath = soundpath + '/ch0' + parseInt(chapnum + 1) + '/';
		else
			soundpath = soundpath + '/ch' + parseInt(chapnum + 1) + '/';
			
		// Load the sound file for current lesson
		$(globalXML).find('[type="Sound Element"]').eq(lessonstatus-1).is(function () {
			$("#player2").attr("src", soundpath + $(this).find('[name="soundQuestion"]').text());
			var newLessonDesc = $(this).find('[name="textQuestion"]').text();
			$("#lesson-desc").html(newLessonDesc);
			sendTranslationRequest();
			$("#part select")[0].selectedIndex = lessonstatus - 1;
		});
		
		resetPauseLesson();
		player.play();
		
	}

	var sourceLang = "fr";
	var destLang = "en";
		
	function receiveTranslation(response) 
	{
	<?php 
		if ($translateLessonDesc == "true") {
	?>
		document.getElementById("translated-lesson-desc").innerHTML = response.data.translations[0].translatedText;
	<?php 
		}
	?>
	}
			
	function sendTranslationRequest() 
	{
	<?php 
		if ($translateLessonDesc == "true") {
	?>
			var newScript = document.createElement('script');
			newScript.type = 'text/javascript';
			var sourceText = encodeURI(document.getElementById("lesson-desc").innerHTML);
			var source = 'https://www.googleapis.com/language/translate/v2?key=AIzaSyB167do9V2DZgEgzdVUz5Atkms2VUXxwk8&source=' + sourceLang + 
					'&target=' + destLang + 
					'&callback=receiveTranslation&q=' + sourceText;
			newScript.src = source;
			document.getElementsByTagName('head')[0].appendChild(newScript);
	<?php 
		}
	?>
	}

	function proceedNextLessonWDelay() 
	{
		if(!isPaused) {
			var delay = (getRecordingTime() < 2.0) ? 1000 : 0;
			setTimeout(proceedNextLesson, delay);
		}
	}
	
	function proceedNextLesson()
	{
		$("#current-step").html("Step 1: Listen carefully");
		// comment the dynamic instruction out for the first step because changing that
		// and the word at the same time causes visual confusion
		if (lessonstatus < numparts)
		{
			lessonstatus++;
			onButtonClick(2);
		} else {
			if ($('#auto_advance').prop('checked') == true) {
				proceedNextExercise();
			}
			else {
				var nextChapter = document.getElementById('chapter-selector').selectedIndex + 1;
				alertMessage("Chapter " + nextChapter + " Completed!", "success");
				$("#current-step").html("");
			}
		}
	}
	
	function onPauseClick()
	{
		recorderProgress = 0;
		clearInterval(recorderTimer);
		$(function () {
			$("#progressbar").progressbar({
				value: recorderProgress
			});
		});
		if ($('#pausebutton').html() == "Pause Exercise")
		{
			player.pause();
			Wami.stopRecording();
			$('#pausebutton').html('Resume Exercise');
			isPaused = true;
		}
		else
		{
			isPaused = false;
			$('#pausebutton').html('Pause Exercise');
			if (lessonstatus == 0)
				player.play();
			else
				setTimeout(restartCurrentSample, 700);
		}
	}

	function resetPauseLesson() 
	{
		// Stop and reset the recorder
		recorderProgress = 0;
		clearInterval(recorderTimer);
		$(function () {
			$("#progressbar").progressbar({
				value: recorderProgress
			});
		});
		isPaused = false;
		$("#current-step").html("Step 1: Listen carefully");
		$('#pausebutton').html('Pause Exercise');
	}

	function restartCurrentSample() 
	{
		onButtonClick(2);
	}

	// use pure js because jquery's handling of <select> is quite ungraceful
	function proceedNextChapter() 
	{
		var nextChapter = document.getElementById('chapter-selector').selectedIndex + 1;
		if (nextChapter < document.getElementById('chapter-selector').length) {
			alertMessage("Chapter " + nextChapter + " Completed! Proceeding to next chapter...", "success");
			document.getElementById('chapter-selector').selectedIndex = nextChapter;
			onChapterMenuSelect(nextChapter);		
		}
		else
		{
			alertMessage("Congratulations... You have completed all the lessons!", "success");
			$("#current-step").html("");
		}
	}

	function proceedNextExercise() 
	{
		var nextExercise = document.getElementById('exercise-selector').selectedIndex + 1;
		if (nextExercise < document.getElementById('exercise-selector').length) {
			alertMessage("Proceeding to next exercise!", "info");
			document.getElementById('exercise-selector').selectedIndex = nextExercise;
			onExerciseMenuSelect(nextExercise);
		} else {
			proceedNextChapter();
		}
	}
	
	function onChapterMenuSelect(buttonindex)
	{	
		resetPauseLesson();
		chapnum = buttonindex;
		exernum = 0;
		newChapterSelected = 1;
		getChapterXML(buttonindex, exernum);
	}
	
	function onExerciseMenuSelect(buttonindex)
	{	
		resetPauseLesson();
		exernum = buttonindex;
		getChapterXML(chapnum, exernum);
	}
	
	function onPartMenuSelect(buttonindex)
	{
		lessonstatus = buttonindex + 1;
		
		var soundpath = baseurl;
		if (chapnum < 9)
			soundpath = soundpath + '/ch0' + parseInt(chapnum + 1) + '/';
		else
			soundpath = soundpath + '/ch' + parseInt(chapnum + 1) + '/';
			
		$(globalXML).find('[type="Sound Element"]').eq(buttonindex).is(function () {
			$("#player2").attr("src", soundpath + $(this).find('[name="soundQuestion"]').text());
			var newLessonDesc = $(this).find('[name="textQuestion"]').text();
			$("#lesson-desc").html(newLessonDesc);
			sendTranslationRequest();
		});

		resetPauseLesson();
		player.play();
	}
	
	function replay() {
		isReplaying = true;
		player.play();
	}
	
	function playRecording() {
		Wami.startPlaying(generatePlayPath(), null, Wami.nameCallback(proceedNextLessonWDelay));
	}
	
	function RecorderProgressBar()
	{
		recorderProgress = 0;
		if (lessonstatus != 0) { /* PROGRESS BAR TIMER */
			recorderTimer = setInterval(updateProgressbar, (getRecordingTime() * 10)); // millisec * 1000 / 100

			// STEP 2
			$("#current-step").html("Step 2: Record your voice");
			Wami.startRecording(generateUploadPath());
		}
		else
		{
			clearInterval(recorderTimer);
			proceedNextLesson();	
		}
		
		function updateProgressbar(){
			$("#progressbar").progressbar({
				value: ++recorderProgress
			});
			if(recorderProgress == 100)
			{
				clearInterval(recorderTimer);
				Wami.stopRecording();

				// STEP 3
				$("#current-step").html("Step 3: Listen again and compare");
				setTimeout(replay, estimateCommunicationLag(getRecordingTime()));
			}
		}
		
		$(function () {
			$("#progressbar").progressbar({
				value: recorderProgress
			});
		});
	} 
	
	function alertMessage(msg, type)
	{
	  $(document).trigger("add-alerts", [
		{
		  'message': msg,
		  'priority': type
		}
	  ]);
	}

	</script>
</head>

<body>
<div id="module-wrapper">
	<div id="header">
		<div id="chapter">
		<span>Chapter</span>
			<select id="chapter-selector" onChange="onChapterMenuSelect(this.selectedIndex);">
			</select>	
		</div>
		<div id="exercise">
		    <span>Exercise</span>
			<select id="exercise-selector" onChange="onExerciseMenuSelect(this.selectedIndex);">
			</select>		
		</div>
		<div id="part">
			<span>Sample</span>
			<select id="part-selector" onChange="onPartMenuSelect(this.selectedIndex);">
			</select>		
		</div>
	</div>
	<div id="body">
		<div id="instructions">
			<h3>Instructions</h3>
			<div id="instructions-box">
			</div>
		</div>
		<div id="dialouge">
			<h3>&nbsp;<span id="current-step"></span></h3>
			<div id="dialouge-box">
				<div id="audio">
					<audio id="player2" src="audio/part01.mp3" type="audio/mp3" controls="controls">
					</audio>
					<br>
					<br>
					<div id="lesson-desc" id="lesson-desc">Repeat the text here after the instructor's recording</div>
<?php 
					if ($translateLessonDesc == true) {
?>
						<br>
						<div id="lesson-desc-translation" id="translated-lesson-desc"></div>
<?php 
					}
?>
					<div id="wami" style=""></div>
					<noscript>The Pronuciation Tool requires Javascript</noscript>
				</div>
				<div id="lesson-controls">
					<button type="button" onclick="onButtonClick(3)" id="restart">Start from Beginning</button>
					<img src="images/headphone.png"></img>
					<button type="button" id="pausebutton" onclick="onPauseClick()">Pause Exercise</button>
				</div>
			</div>
			<br>
			<div id="progressbar">
				<div id="progressbar_foreground">
					Time left to record your voice
				</div>
			</div>
		</div>
		<div id="messagebox" data-alerts="alerts" data-ids="myid" data-fade="5000"></div>
	</div>
	<div id="footer">
	<span id="extra_recording_time_control">
		Recording time:&nbsp;
		<select id="extra_recording_time_select">
			<option selected>auto</option>
			<option>1</option>
			<option>2</option>
			<option>3</option>
			<option>4</option>
			<option>5</option>
		</select>
		&nbsp;secs&nbsp;
		<input type="checkbox" name="auto_advance" id="auto_advance">Autoplay all exercises</input>
	</span>
	Press "start from beginning" to skip instructions
	</div>
</div>
<script>
// Creating the mediaelementplayer JavaScript object for later use
var player = new MediaElementPlayer('#player2', {
					success: function(player, node) {
						player.addEventListener('ended', function(e){
							if (!isReplaying) {
								RecorderProgressBar();
							} else {
								// STEP 4
								 $("#current-step").html("Step 4: Listen to your voice again");
								if (lessonstatus != 0) {
									playRecording();
								}
								isReplaying = false;
							}
						});

						player.addEventListener('error', function(e){
							alertMessage("some audio files are missing from this lesson\n", "error");
							if ($('#auto_advance').prop('checked') == true) {
								proceedNextExercise();
							}	
						});
					},					
					error: function() {
						alertMessage("initiate player failed\n", "error");
					},
					features: ['progress','volume']
				});
</script>
</body>
</html>