<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\TextToSpeechLongAudioSynthesizeClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\Storage\StorageClient;
use App\Models\PDF;

class TTSController extends Controller
{
    private $audioState = 'stopped';
    private $stream = null;
	
    public function convert(Request $request)
    {
        $pdfId = $request->input('id');
        try {
            $pdf = PDF::findOrFail($pdfId);
        } catch (\Exception $e) {
            return response()->json(['error' => 'PDF not found'], 404);
        }

        if($pdf->audio_path != null){
            return response()->json(['message' => 'TTS conversion successful', 'audio_path' => Storage::disk('public')->url($pdf->audio_path)]);
        }
		try{
        if($pdf->content != null){
            $text = $pdf->content;
        }else{
            $text = $this->extractTextFromPdf($pdf->path);
        }
		}catch (\Exception $e){
			return response()->json(['error' => 'PDF file is large. Try to upload small file'], 404);
		}
		$textLength = (int)strlen($text);
		if($textLength > 5000){
			$audioPath = $this->longTextToSpeech($pdfId,$text);
		}else{
			$audioPath = $this->smallTextToSpeech($pdfId,$text);
		}

		if($audioPath){
			return response()->json(['message' => 'TTS conversion successful', 'audio_path' => Storage::disk('public')->url($audioPath)],200);
		}else{
			return response()->json(['error' => 'PDF file is large. Try to upload small file'], 404);
		}
    }
	
	private function split_text($text, $max_length = 5000) {
		$chunks = [];
		while (strlen($text) > $max_length) {
			$split_pos = strrpos(substr($text, 0, $max_length), ' ');
			if ($split_pos === false) {
				$split_pos = $max_length;
			}
			$chunks[] = substr($text, 0, $split_pos);
			$text = substr($text, $split_pos + 1);
		}
		$chunks[] = $text;
		return $chunks;
	}
	
	private function smallTextToSpeech($pdfId,$text){
		$client = new TextToSpeechClient();
        $inputText = new SynthesisInput();
        $inputText->setText($text);

        $languageCode = 'en-US';
        $voiceName = 'en-US-Wavenet-D';
        $audioEncoding = AudioEncoding::MP3;

        $voice = new VoiceSelectionParams();
        $voice->setLanguageCode($languageCode);
        $voice->setName($voiceName);

        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding($audioEncoding);
        $response = $client->synthesizeSpeech($inputText, $voice, $audioConfig);
        $audioContent = $response->getAudioContent();
		
		$pdf = PDF::findOrFail($pdfId);
        $audioPath = 'tts-output-' . $pdfId . '.mp3';
        Storage::disk('public')->put($audioPath, $audioContent);
        $aUrl = Storage::disk('public')->url($audioPath);
        $pdf->audio_path = $audioPath;
        $pdf->save();
		return $audioPath;
	}

	private function longTextToSpeech($pdfId, $text) {
		$textChunks = $this->split_text($text);

		$languageCode = 'en-US';
		$voiceName = 'en-US-Wavenet-D';
		$audioEncoding = AudioEncoding::MP3;

		$voice = (new VoiceSelectionParams())
			->setLanguageCode($languageCode)
			->setName($voiceName);

		$audioConfig = (new AudioConfig())->setAudioEncoding($audioEncoding);

		$textToSpeechClient = new TextToSpeechClient();
		$audioPaths = [];

		foreach ($textChunks as $index => $chunk) {
			$synthesisInput = (new SynthesisInput())->setText($chunk);
			$audioPath = 'tts-output-' . $pdfId . '-' . $index . '.mp3';
			$response = $textToSpeechClient->synthesizeSpeech($synthesisInput, $voice, $audioConfig);
			$audioContent = $response->getAudioContent();

			// Save the chunk audio content to a local file
			Storage::disk('public')->put($audioPath, $audioContent);
			$audioPaths[] = storage_path('app/public/' . $audioPath);
		}

		// Combine all audio files into one
		$finalAudioPath = 'tts-output-' . $pdfId . '.mp3';
		$this->combineAudioFiles($audioPaths, storage_path('app/public/' . $finalAudioPath));

		// Save the final audio path to the database
		$pdf = PDF::findOrFail($pdfId);
		$pdf->audio_path = $finalAudioPath;
		$pdf->save();

		return $finalAudioPath;
	}

	private function combineAudioFiles(array $audioPaths, $outputPath) {
		$concatFile = storage_path('app/public/audio_list.txt');
		$fileHandle = fopen($concatFile, 'w');
		foreach ($audioPaths as $audioPath) {
			fwrite($fileHandle, "file '" . str_replace('\\', '/', $audioPath) . "'\n");
		}
		fclose($fileHandle);

		// For windows
		$ffmpegPath = env('FFMPEG_PATH');; //storage_path('app/public/ffmpeg.exe');
		$command = "\"$ffmpegPath\" -y -f concat -safe 0 -i " . escapeshellarg($concatFile) . " -c copy " . escapeshellarg($outputPath);
		
		// For Linux
		//$command = 'ffmpeg -y -f concat -safe 0 -i ' . escapeshellarg($concatFile) . ' -c copy ' . escapeshellarg($outputPath);
		
		shell_exec($command);

		// Optionally delete the chunk audio files and the concat list
		foreach ($audioPaths as $audioPath) {
			unlink($audioPath);
		}
		unlink($concatFile);
	}
	
    private function extractTextFromPdf($filePath)
    {
        $fullPath = storage_path('app/public/' . $filePath);
        $pdfParser = new \Smalot\PdfParser\Parser();
        $pdf = $pdfParser->parseFile($fullPath);
        $text = $pdf->getText();
        return $text;
    }

    public function play(Request $request)
    {
        $pdfId = $request->input('id');
        $pdf = PDF::findOrFail($pdfId);
        if ($this->audioState === 'stopped' || $this->audioState === 'paused') {
            $this->audioState = 'playing';
            $audioPath = $pdf->audio_path;
            return $this->streamAudio($audioPath);
        } else {
            return response()->json(['error' => 'Audio is already playing'], 400);
        }
    }

    public function pause(Request $request)
    {
        $this->audioState = 'paused';
        return response()->json(['message' => 'Pause request received'],200);
    }

    public function stop(Request $request)
    {
        $this->audioState = 'stopped';
        if ($this->stream !== null && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
        return response()->json(['message' => 'Stop request received'],200);
    }

    private function streamAudio($audioPath)
    {
//        $stream = function () use ($audioPath) {
//            $this->stream = Storage::disk('public')->readStream($audioPath);
//            fpassthru($this->stream );
//            if (is_resource($this->stream )) {
//                fclose($this->stream );
//            }
//        };

        $this->stream = Storage::disk('public')->readStream($audioPath);
        return response()->stream(function () {
            fpassthru($this->stream);
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="'.$audioPath.'"'
        ]);
    }
}

