<?php
@ini_set('log_errors', 1);
@ini_set('display_errors', 0);
@error_reporting(E_ALL | E_NOTICE);
set_time_limit(0);
set_error_handler(create_function('$a, $b, $c, $d', 'throw new ErrorException($b, 0, $a, $c, $d);'), E_ERROR | E_RECOVERABLE_ERROR | E_USER_ERROR | E_USER_WARNING | E_WARNING);

function exception_handler($exception) {
	error_log($exception->__toString());
}

set_exception_handler('exception_handler');

require_once dirname(__FILE__).'/api/external.php';

@ini_set('error_log', Storm::ToAbsolute('/../logs/php_errors.txt'));

$backup_model = StormModel::getInstance('backup_model');
$log_model = StormModel::getInstance('log_model');

$data = $backup_model->getData();
$jobs = $backup_model->getBackupJobsToStart($data);

if (count($jobs) == 0)
{
	exit;
}

echo count($jobs) . " jobs should be backed up @ " . strftime("%d.%m.%Y %H:%M") . PHP_EOL;

foreach ($jobs as $backup)
{
	try
	{
		$logfile = Storm::ToAbsolute('/../logs/'. $backup_model->parseTitle($backup->title) .'.txt');

		$backup->InProgress = true;
		$backup->LastBackup = strtotime('now');
		$backup_model->storeData($data);

		$log_model->Log($logfile, "Starting backup...");

		echo "Backing up job '" . $backup->title . "'..." . PHP_EOL;

		$warnings = $backup_model->backup($backup);

		if (is_array($warnings) && count($warnings) > 0)
		{
			$backup->warnings = $warnings;
			echo "There were ". count($warnings) ." warnings!". PHP_EOL;

			foreach ($warnings as $msg)
			{
				$log_model->Log($logfile, "WARNING: ". $msg);
			}
		}

		$oldCount = $backup_model->clearOlderArchives($backup);
		if ($oldCount !== true && $oldCount !== 0)
		{
			echo "Removed ". $oldCount ." old archives" . PHP_EOL;
		}

		$backup->InProgress = false;

		$log_model->Log($logfile, "Backup successful");

		// Send requested emails
		if ($backup->emailMe)
		{
			try
			{
				$mail = new PHPMailer();

				$mail->SetFrom($backup->email, 'SmartBackup');
				$mail->AddAddress($backup->email);
				$mail->Subject = "Backup '". $backup->title ."' created at ". strftime("%d.%m.%Y %H:%M");
				$mail->Body = "An archive for '". $backup->title ."' was created successfully!\n\n";
				$mail->Body .= "Time: ". strftime("%d.%m.%Y %H:%M") ."\n";

				if ($oldCount !== true && $oldCount !== 0)
				{
					$mail->Body .= $oldCount ." old archives were removed\n\n";
				}

				if (is_array($warnings) && count($warnings) > 0)
				{
					$backup->Warnings = $warnings;
					$mail->Body .= "There were ". count($warnings) ." warnings:\n";

					foreach ($warnings as $msg)
					{
						$mail->Body .= "- ". $msg ."\n";
					}
				}

				if(!$mail->Send())
				{
					$msg = "Mailer Error: " . $mail->ErrorInfo;
					$log_model->Log($logfile, $msg);

					echo $msg ."\n";
				}
			}
			catch (Exception $e)
			{
				$msg = "There was an error while sending the email report! Exception: ". $e->__toString();

				$log_model->Log($logfile, $msg);

				echo $msg ."\n";
			}
		}
	}
	catch (Exception $e)
	{
		echo "Couldn't backup job '" . $backup->title . "'!" . PHP_EOL;

		$backup->errors[] = array(
			'start' 	=> $backup->LastBackup,
			'end'		=> strtotime('now'),
			'success'	=> false,
			'message'	=> $e->__toString()
		);
		$log_model->Log($logfile, "Backup failed. Exception: " . $e->__toString());

		$backup->InProgress = false;

		if ($backup->emailMe)
		{
			try
			{
				$mail = new PHPMailer();

				$mail->SetFrom($backup->email, 'SmartBackup');
				$mail->AddAddress($backup->email);
				$mail->Subject = "Backup '". $backup->title ."' FAILED at ". strftime("%d.%m.%Y %H:%M");
				$mail->Body = "There was an error while trying to back up '". $backup->title ."'!\n\n";
				$mail->Body .= "Time: ". strftime("%d.%m.%Y %H:%M") ."\n";

				$mail->Body .= "Exception: ". $e->__toString();

				if(!$mail->Send())
				{
					$msg = "Mailer Error: " . $mail->ErrorInfo;
					$log_model->Log($logfile, $msg);
						
					echo $msg ."\n";
				}
			}
			catch (Exception $ee)
			{
				$msg = "There was an error while sending the email ERROR report! Exception: ". $ee->__toString();

				$log_model->Log($logfile, $msg);

				echo $msg ."\n";
			}
		}
	}
}

$backup_model->storeData($data);
echo 'All backup jobs done' . PHP_EOL;