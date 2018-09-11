## Open Parliament Video - Analysis

-------------

Generates time-based transcripts and annotations from parliamentary protocols published via the Bundestag Open Data service ([https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata)) and builds the search index for [https://github.com/parliamentwatch/OpenParliamentVideo-Platform](https://github.com/parliamentwatch/OpenParliamentVideo-Platform).

Implemented for the **[abgeordnetenwatch.de](https://abgeordnetenwatch.de) goes Video** project as part of [demokratie.io](https://demokratie.io/gewinnerprojekte/).

-------------

### Usage

#### Prerequisites

* **PHP**
* [**Aeneas**](https://www.readbeyond.it/aeneas/) ("automagically synchronize audio and text")
    * Aeneas Dependencies: **Python** (2.7.x preferred), **FFmpeg**, and **eSpeak**

#### Step 1: Install Aeneas

See [https://github.com/readbeyond/aeneas/blob/master/wiki/INSTALL.md](https://github.com/readbeyond/aeneas/blob/master/wiki/INSTALL.md).

For Mac OS, there is an all-in-one installer, which takes care of the dependencies: [https://github.com/sillsdev/aeneas-installer/releases](https://github.com/sillsdev/aeneas-installer/releases).

#### Step 2: Configuration

Adjust the settings (directories, dependency paths and detection patterns for annotations) in [server/config.sample.php](server/config.sample.php) and save as `config.php`.

Additionally, make sure the `input`, `output` and `cache` directories exist and are writable.

#### Step 3: Open in Browser

Go to `http://localhost/OpenParliamentVideo-Analysis/` (or wherever you placed the scripts).
In a local environment, you need to start a server first. Any Apache (+ dependencies) should be fine, others weren't tested.
You should see a page with input / output tabs.

#### Step 4: Input Files (XML)

XML input files are located at [input/](input/). To add new files, download the file from  [https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata) and place it into the same directory (or use the file upload).

#### Step 5: Indexation, Alignment & Analysis (aka the magic)

Click "Process Queue" to start indexing, aligning and analysing all speeches in all queued protocol files.

**Careful:** 
Depending on the number of protocol files and speeches, this potentially sends thousands of requests to Bundestag servers and takes some time (just so you have a number: the first 40 session protocols of the 19th electoral period contain roughly 3000 speeches and the process takes between 7-10 hours)! Re-indexing will be quicker as the audio files are cached.

**Explaination:**
During the forced alignment process, we need access to a local copy of the respective audio file (depending on a single speech, agenda item or entire meeting). In order to show a preview upon completion, we also need to get the remote URL of the video file. Both file URLs can be retrieved when the Media ID is known. As Media IDs are not included in the original XML files from [https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata), we need to scrape them from the Bundestag Mediathek RSS Feed and write them to the respective XML nodes (eg. `<rede [...] media-id="1234567">`) and the media index.

#### Step 6: Output Data

The scripts generate 5 files for every speech inside `output/PERIOD/SESSION/`:
- Transcript Timings (JSON)
- Time-based HTML (XHTML)
- Time-based Annotations (JSON-LD)
- Subtitle / Caption Formats (SRT + WebVTT)

The index of already processed speeches is written to `output/index_media.json`.

These files alone are the search index for [https://github.com/parliamentwatch/OpenParliamentVideo-Platform](https://github.com/parliamentwatch/OpenParliamentVideo-Platform).

In order to update the platform index, you have to manually copy everything inside `OpenParliamentVideo-Analysis/output/` to `OpenParliamentVideo-Platform/data/`.

-----------------

### Known Issues

- Functionality is currently limited to the 19th electoral period (previous periods are published in a different format)
- The forced alignment algorithm needs some configuration tweaks to better deal with the first few sentences and non-speech periods.
- The stenographic transcripts are not identical with the spoken word, which can result in offsets and errors when synchronizing text and audio timings.
- In the XML format, there is currently no differentiation between actual speeches (for which a media file exists) and other speaker contributions, for example during electoral proceedings or discussion formats. Agenda items which contain oath taking ("Eidesleistung"), Q&A sessions ("Befragung der Bundesregierung", "Fragestunde") and elections ("Wahl der/des") are thus currently ignored.
- Make sure the `input`, `output` and `cache` directories exist and are writable.
- In certain Windows environments, Python can crash when aligning long speeches. This seems to be a known Aeneas issue. 
