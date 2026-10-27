{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $church->name }}</title>
        <link>{{ route('home') }}</link>
        <atom:link href="{{ route('churches.podcast', $church) }}" rel="self" type="application/rss+xml" />
        <language>pt-br</language>
        <description>Estudos bíblicos de {{ $church->name }}, publicados pelo Didaché. Narração com voz sintética, gerada por inteligência artificial.</description>
        <itunes:author>{{ $church->name }}</itunes:author>
        <itunes:owner>
            <itunes:name>Didaché</itunes:name>
            <itunes:email>{{ config('ebd.podcast_email') }}</itunes:email>
        </itunes:owner>
        <itunes:image href="{{ asset('brand/podcast-capa.png') }}" />
        <itunes:category text="Religion &amp; Spirituality">
            <itunes:category text="Christianity" />
        </itunes:category>
        <itunes:explicit>false</itunes:explicit>
        <itunes:type>episodic</itunes:type>
@foreach ($lessons as $lesson)
        <item>
            <title>{{ $lesson->displayTitle() }}</title>
            <link>{{ route('lessons.show', $lesson->slug) }}</link>
            <guid isPermaLink="false">didache-licao-{{ $lesson->id }}</guid>
            <pubDate>{{ ($lesson->published_at ?? $lesson->created_at)->toRssString() }}</pubDate>
            <description>{{ collect([$lesson->series?->title, $lesson->summary ?? $lesson->goal, $lesson->bible_reference])->filter()->implode(' · ') }}</description>
            <enclosure url="{{ route('lessons.audio', [$lesson->slug, 'v' => $lesson->audio_generated_at?->timestamp]) }}" length="{{ $sizes[$lesson->id] }}" type="audio/mpeg" />
@if ($lesson->audio_duration)
            <itunes:duration>{{ $lesson->audio_duration }}</itunes:duration>
@endif
@if ($lesson->number)
            <itunes:episode>{{ $lesson->number }}</itunes:episode>
@endif
            <itunes:explicit>false</itunes:explicit>
        </item>
@endforeach
    </channel>
</rss>
