<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
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
        if($pdf->content != null){
            $text = $pdf->content;
        }else{
            $text = $this->extractTextFromPdf($pdf->path);
        }

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

        $audioPath = 'tts-output-' . $pdfId . '.mp3';
        Storage::disk('public')->put($audioPath, $audioContent);
        $aUrl = Storage::disk('public')->url($audioPath);
        $pdf->audio_path = $audioPath;
        $pdf->save();

        return response()->json(['message' => 'TTS conversion successful', 'audio_path' => Storage::disk('public')->url($audioPath)],200);
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
        $stream = function () use ($audioPath) {
            $this->stream = Storage::disk('public')->readStream($audioPath);
            fpassthru($this->stream );
            if (is_resource($this->stream )) {
                fclose($this->stream );
            }
        };

        return response()->stream(function ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="'.$audioPath.'"'
        ]);
    }
}

