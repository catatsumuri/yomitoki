<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'IPAGothic';
            font-style: normal;
            font-weight: normal;
            src: url('{{ resource_path('fonts/ipag.ttf') }}') format('truetype');
        }

        body { font-family: IPAGothic, DejaVu Sans, sans-serif; font-size: 12pt; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; }
        .page { padding: 40px 50px; }
        .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 24px; }
        h1 { font-size: 22pt; font-weight: bold; margin: 0 0 8px 0; color: #111827; }
        .meta { font-size: 9pt; color: #6b7280; margin-bottom: 6px; }
        .tag { display: inline-block; background: #f3f4f6; color: #374151; font-size: 8pt; padding: 2px 6px; margin-right: 4px; }
        .summary { font-size: 11pt; color: #4b5563; font-style: italic; margin-bottom: 24px; padding: 12px 16px; border-left: 3px solid #d1d5db; background: #f9fafb; }
        .content { font-size: 11pt; }
        .content h1 { font-size: 16pt; font-family: IPAGothic, DejaVu Sans, sans-serif; }
        .content h2 { font-size: 14pt; font-family: IPAGothic, DejaVu Sans, sans-serif; }
        .content h3 { font-size: 12pt; font-family: IPAGothic, DejaVu Sans, sans-serif; }
        .content h4, .content h5, .content h6 { font-family: IPAGothic, DejaVu Sans, sans-serif; }
        .content td, .content th { font-family: IPAGothic, DejaVu Sans, sans-serif; }
        .content p { margin: 0 0 10px 0; }
        .content ul, .content ol { padding-left: 20px; margin-bottom: 10px; }
        .content code { background: #f3f4f6; font-family: IPAGothic, DejaVu Sans Mono, monospace; font-size: 9pt; padding: 1px 4px; }
        .content pre { background: #f3f4f6; padding: 10px 12px; font-size: 9pt; margin-bottom: 12px; font-family: IPAGothic, DejaVu Sans Mono, monospace; }
        .content blockquote { border-left: 3px solid #d1d5db; padding-left: 12px; color: #6b7280; margin: 0 0 10px 0; }
        .content table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10pt; }
        .content th, .content td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; }
        .content th { background: #f9fafb; font-weight: bold; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 8pt; color: #9ca3af; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">{{ ucfirst($documentType) }} &bull; Created {{ $createdAt->format('F j, Y') }}</div>
        @if(count($tags) > 0)
        <div style="margin-bottom:8px">
            @foreach($tags as $tag)<span class="tag">#{{ $tag }}</span>@endforeach
        </div>
        @endif
    </div>
    @if($summary)
    <div class="summary">{{ $summary }}</div>
    @endif
    @if($contentHtml)
    <div class="content">{!! $contentHtml !!}</div>
    @endif
    <div class="footer">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</div>
</div>
</body>
</html>
