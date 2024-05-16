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
    public function convert(Request $request)
    {
        $pdfId = $request->input('id');
        $pdf = PDF::findOrFail($pdfId);

        if($pdf->audio_path != null){
            return response()->json(['message' => 'TTS conversion successful', 'audio_path' => Storage::disk('public')->url($pdf->audio_path)]);
        }
        //$pdfText = $this->extractTextFromPdf($pdf->path);
        $text = $pdf->content;

        $client = new TextToSpeechClient();
        $inputText = new SynthesisInput();
        $inputText->setText($text);


        $languageCode = 'en-US';
        $voiceName = 'en-US-Wavenet-D';
        $audioEncoding = AudioEncoding::MP3;
        // Build the voice request, select the language code and the name of the voice
        $voice = new VoiceSelectionParams();
        $voice->setLanguageCode($languageCode);
        $voice->setName($voiceName);

        // Select the type of audio file you want returned
        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding($audioEncoding);

        // Perform the text-to-speech request
        $response = $client->synthesizeSpeech($inputText, $voice, $audioConfig);

        // Get the audio content from the response
        $audioContent = $response->getAudioContent();

        $audioPath = 'tts-output-' . $pdfId . '.mp3';
        Storage::disk('public')->put($audioPath, $audioContent);
        $aUrl = Storage::disk('public')->url($audioPath);
        $pdf->audio_path = $audioPath;
        $pdf->save();

        return response()->json(['message' => 'TTS conversion successful', 'audio_path' => Storage::disk('public')->url($audioPath)]);
    }

    private function extractTextFromPdf($filePath)
    {
        $fullPath = storage_path('app/' . $filePath);
        $pdfParser = new \Smalot\PdfParser\Parser();
        $pdf = $pdfParser->parseFile($fullPath);
        $text = $pdf->getText();
        return $text;
    }

    public function play(Request $request)
    {
        return response()->json(['message' => 'Play request received']);
    }

    public function pause(Request $request)
    {
        return response()->json(['message' => 'Pause request received']);
    }

    public function stop(Request $request)
    {
        return response()->json(['message' => 'Stop request received']);
    }
}

