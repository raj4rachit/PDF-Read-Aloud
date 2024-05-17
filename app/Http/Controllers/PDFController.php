<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\PDF;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

class PDFController extends Controller
{
    public function index()
    {
        $pdfFiles = PDF::get();
        return view('upload', compact('pdfFiles'));
    }

    public function upload(Request $request)
    {
		try{
        $request->validate(['pdf' => 'required|mimes:pdf|max:10000']);
        $file = $request->file('pdf');

        $filename = $file->hashName();
        $path = $file->storeAs('public/pdfs', $filename);
        $publicPath = 'pdfs/' . $filename;

        $pdfParser = new Parser();
        $pdf = $pdfParser->parseFile($file->path());
        $content = $pdf->getText();
        //$path = $file->store('pdfs');

        $pdf = PDF::create(['path' => $publicPath, 'content' => $content]);
        return redirect()->route('pdf.show', $pdf->id);
		}catch (\Exception $e){
			return response()->json(['error' => 'PDF file is large. Try to upload small file'], 404);
		}
    }

    public function show($id)
    {
        $pdf = PDF::findOrFail($id);
        return view('pdf', compact('pdf'));
    }

    public function read($id)
    {
        $pdf = PDF::findOrFail($id);
        $filePath = storage_path('app/' . $pdf->path);

        $pdfParser = new Fpdi();
        $pageCount = $pdfParser->setSourceFile($filePath);
        $text = '';

        for ($i = 1; $i <= $pageCount; $i++) {
            $page = $pdfParser->importPage($i);
            $pdfParser->useTemplate($page);
            $text .= $pdfParser->getText();
        }
    }
    public function destroy($id)
    {
        try {
            $pdfFile = PDF::findOrFail($id);
            Storage::delete('public/' . $pdfFile->path);
            if($pdfFile->audio_path != null){
                Storage::delete('public/' . $pdfFile->audio_path);
            }
            $pdfFile->delete();
            return redirect()->back()->with('success', 'PDF file deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete PDF file: ' . $e->getMessage());
        }
    }
}
