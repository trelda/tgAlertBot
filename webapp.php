<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require realpath(dirname(__FILE__)) . '/connect.php';
global $mysqli;
echo "
<html>
	<head>
		<script src='https://telegram.org/js/telegram-web-app.js'></script>
		<script src='jquery.min.js'></script> 
	</head>
	<body id='bodyMain'>
		<style>
		#bodyMain {
			padding: 10px;
			margin: 20px;
			border:1px solid #ccc;
			border-radius: 5px;
		}
		.item {
			display: flex;
			flex-direction: column;
			margin-bottom:30px;
			background: #fff;
			padding: 30px;
		}
		.item:hover {
			background: #ccc;
		}
		.mainFormHeaderText {
			font-size:18px;
		}
		.selectDate {
			width: 100%;
			margin: 10px 0px;
			padding: 5px;
			border: 2px solid #ccc;
			border-radius: 10px;
			outline: 0px;
		}
		.selectDate:focus {
			border: 2px solid green;
		}
		button, .submit {
            display: block;
            font-size: 14px;
            margin: 15px 0;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            background-color: var(--tg-theme-button-color, #50a8eb);
            color: var(--tg-theme-button-text-color, #ffffff);
            cursor: pointer;
        }
		.line {
			display: flex;
			flex-direction: column;
		}
		.contentText {
			margin-bottom: 20px;
			font-size: 20px;
			border-bottom: 1px solid #ccc;
			padding-bottom: 10px;
		}
		.errMsg {
			color: red;
		}
		</style>
		<div class='content'>
		<div class='contentText'>Для создания отчета по сообщениям выберите нужный диапазон дат:</div>
			<div class='line'>
				<span class='mainFormHeaderText'>Дата начала:</span>
				<input id='dateFrom' name='dateFrom' type='datetime-local' class='selectDate' value='2022-08-01T00:00'/>
			</div>
			<div class='line'>
				<span class='mainFormHeaderText'>Дата окончания:</span>
				<input id='dateTo' name='dateTo' type='datetime-local'  class='selectDate'/>
			</div>
			<button id='createReport' name='createReport' onclick='sendBack();'>Создать отчет</button>
		</div>	
		<div class='errMsg' id='result'></div>

	<script>
		function setThemeClass() {
			document.documentElement.className = Telegram.WebApp.colorScheme;
		}
		Telegram.WebApp.onEvent('themeChanged', setThemeClass);
		setThemeClass();
	</script>
	
	<script type='application/javascript'>
    Telegram.WebApp.ready();
	const initData = Telegram.WebApp.initData || '';
	const initDataUnsafe = Telegram.WebApp.initDataUnsafe || {};
	var predlIsDown = false;
	setDates();
    if (!Telegram.WebApp.initDataUnsafe || !Telegram.WebApp.initDataUnsafe.query_id) {
		predlIsDown = true;
	}
	if (predlIsDown != false) {
		document.querySelector('#bodyMain').innerHTML = '';//web
		} else {
			const userId = initDataUnsafe.user.id;
			const mainButton = Telegram.WebApp.MainButton;
			Telegram.WebApp.MainButton
				.setText('Продолжить')
				.hide()
				.onClick(function(){ webviewClose(); });
		}
		
    function webviewClose() {
        Telegram.WebApp.close();
    };
	
	function sendBack () {
		let errMsg = '';
		const userId = initDataUnsafe.user.id;
		document.querySelector('#result').innerHTML = '';		
		let dateFrom = document.getElementById('dateFrom').value;
		let dateTo = document.getElementById('dateTo').value;
		if (dateFrom=='') errMsg = errMsg + 'Не выбрана дата начала<br>';
		if (dateTo=='') errMsg = errMsg + 'Не выбрана дата окончания<br>';
		var content = {
			type: 'getrep',
			dateFrom: document.getElementById('dateFrom').value,
			dateTo: document.getElementById('dateTo').value,
			user: encodeURI(JSON.stringify(initDataUnsafe.user)),
			query_id: initDataUnsafe.query_id,
			authdate: initDataUnsafe.auth_date,
			hash: initDataUnsafe.hash,
			userid: initDataUnsafe.user.id
		}
		$.ajax({
			url: 'ajax.php',
			method: 'post',
			dataType: 'html',
			data: content,
			success: function(data){
				if (data==='correct') {
					document.querySelector('#result').innerHTML =('Отчет сформирован и передан в телеграм.');
					Telegram.WebApp.MainButton
					.setText('Закрыть')
					.show();
				} else {
					document.querySelector('#result').innerHTML =('Ошибка создания отчета.');
				}
			}
		});
	};
	
	function setDates() {
		var currentdate = new Date();
		let nowTime = currentdate.getFullYear() + '-0' + (currentdate.getMonth()+1).toString().slice(-2) + '-0' + currentdate.getDate().toString().slice(-2) + 'T' + currentdate.getHours().toString().slice(-2) + ':' + '0' + currentdate.getMinutes().toString().slice(-2);
		document.getElementById('dateTo').value = nowTime;
	};
	</script>
	</body>	
</html>	
";
?>