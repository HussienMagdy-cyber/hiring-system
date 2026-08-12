<?php
if (!defined('ABSPATH')) exit;

function hs_calculate_score($app_id, $candidate_id, $job_id) {
    global $wpdb;
    $candidate = HS_Candidate::find($candidate_id);
    $job = HS_Job::find($job_id);
    if (!$candidate || !$job) return 0;

    $weights = HS_Settings::get('scoring_weights');
    $score = 0;

    HS_Score::delete_by_application($app_id);

    // Experience (35%)
    $exp_score = min(($candidate->experience_years / 10) * 100, 100);
    $exp_final = $exp_score * $weights['experience'];
    $score += $exp_final;
    HS_Score::save([
        'application_id' => $app_id,
        'category' => 'experience',
        'value' => round($exp_score, 2),
        'weight' => $weights['experience'],
        'final_score' => round($exp_final, 2),
    ]);

    // Skills matching against job requirements (40%)
    $skills_score = hs_calculate_skills_score($candidate->skills, $job->requirements);
    $skills_final = $skills_score * $weights['skills'];
    $score += $skills_final;
    HS_Score::save([
        'application_id' => $app_id,
        'category' => 'skills',
        'value' => round($skills_score, 2),
        'weight' => $weights['skills'],
        'final_score' => round($skills_final, 2),
    ]);

    // Education (10%)
    $edu_levels = ['high-school' => 1, 'associate' => 2, 'bachelor' => 3, 'master' => 4, 'phd' => 5];
    $edu_score = isset($edu_levels[$candidate->education_level]) ? ($edu_levels[$candidate->education_level] / 5 * 100) : 50;
    $edu_final = $edu_score * $weights['education'];
    $score += $edu_final;
    HS_Score::save([
        'application_id' => $app_id,
        'category' => 'education',
        'value' => round($edu_score, 2),
        'weight' => $weights['education'],
        'final_score' => round($edu_final, 2),
    ]);

    // Portfolio (15%)
    $port_score = !empty($candidate->portfolio_url) ? 100 : 50;
    $port_final = $port_score * $weights['portfolio'];
    $score += $port_final;
    HS_Score::save([
        'application_id' => $app_id,
        'category' => 'portfolio',
        'value' => round($port_score, 2),
        'weight' => $weights['portfolio'],
        'final_score' => round($port_final, 2),
    ]);

    $final = round($score, 2);
    HS_Application::update_score($app_id, $final);

    $auto_reject = (float)HS_Settings::get('auto_reject_score');
    if ($auto_reject > 0 && $final < $auto_reject) {
        HS_Application::update_status($app_id, 'rejected', __('Auto-rejected: Score below threshold', 'hiring-system'));
    }

    return $final;
}

function hs_calculate_skills_score($candidate_skills, $job_requirements) {
    if (empty($candidate_skills)) return 30;

    $candidate_arr = array_filter(array_map('trim', explode(',', strtolower($candidate_skills))));
    if (empty($candidate_arr)) return 30;

    $req_text = strtolower(strip_tags($job_requirements ?: ''));
    $req_skills = [];
    $common_skills = ['php','javascript','python','java','react','vue','angular','wordpress','laravel','django','node','sql','mysql','postgresql','mongodb','aws','docker','kubernetes','git','html','css','sass','typescript','go','rust','ruby','rails','c++','c#','.net','swift','kotlin','flutter','react native','android','ios','figma','sketch','photoshop','illustrator','seo','marketing','sales','leadership','management','agile','scrum','jira','confluence','excel','powerpoint','word','outlook','salesforce','hubspot','analytics','data analysis','machine learning','ai','nlp','devops','ci/cd','jenkins','terraform','ansible','linux','ubuntu','nginx','apache','redis','elasticsearch','graphql','rest api','soap','xml','json','oauth','jwt','stripe','paypal','woocommerce','shopify','magento','drupal','joomla','bootstrap','tailwind','jquery','backbone','ember','svelte','next.js','nuxt','gatsby','electron','cordova','ionic','xamarin','unity','unreal','blender','maya','3ds max','autocad','solidworks','catia','matlab','r','spss','sas','stata','tableau','power bi','looker','snowflake','bigquery','redshift','spark','hadoop','kafka','rabbitmq','celery','varnish','cloudflare','akamai','fastly','new relic','datadog','grafana','prometheus','elk','splunk','pagerduty','opsgenie','slack','teams','zoom','webex','meet','discord','telegram','whatsapp','trello','asana','monday','notion','clickup','basecamp','wrike','smartsheet','airtable','zapier','make','n8n','ifttt','workato','mulesoft','boomi','talend','informatica','pentaho','knime','rapidminer','weka','orange','tensorflow','pytorch','keras','scikit-learn','pandas','numpy','scipy','matplotlib','seaborn','plotly','bokeh','dash','streamlit','gradio','huggingface','openai','anthropic','cohere','stability','midjourney','dall-e','runway','synthesia','heygen','elevenlabs','descript','capcut','premiere','after effects','davinci resolve','final cut','avid','logic pro','ableton','fl studio','pro tools','cubase','nuendo','reason','studio one','bitwig','reaper','garageband','audacity','adobe audition','sound forge','waves','fabfilter','izotope','native instruments','serato','traktor','rekordbox','pioneer dj','denon dj','numark','rane','technics','pioneer','yamaha','roland','korg','nord','novation','arturia','sequential','moog','dave smith','oberheim','waldorf','access','virus','elektron','teenage engineering','op-1','po-33','subpac','sony','canon','nikon','fujifilm','panasonic','blackmagic','red','arri','sony venice','canon c70','panasonic s1h','fujifilm gfx','hasselblad','phase one','leica','sigma','tamron','tokina','zeiss','rokinon','samyang','laowa','venus optics','ttartisan','7artisans','pergear','neewer','godox','profoto','elinchrom','broncolor','aputure','nanlite','amaran','luxli','quasar','astera','dmx','artnet','sacn','madrix','resolume','arena','touchdesigner','vvvv','processing','openframeworks','cinder','unity3d','unreal engine','cryengine','godot','defold','gamemaker','construct','rpg maker','renpy','twine','ink','harlowe','sugarcube','bitsy','pico-8','tic-80','wasm-4','lowres nx','fantasy console','tic80','liko-12','pixel vision 8','pygame','love2d','raylib','sfml','sdl','glfw','glew','glad','imgui','nuklear','microui','dear imgui','implot','imnodes','imguizmo','assimp','tinygltf','cgltf','draco','basis universal','ktx','dds','hdr','exr','tga','bmp','png','jpg','webp','avif','gif','apng','svg','pdf','eps','ai','psd','xcf','ora','kra','clip','sai','medibang','firealpaca','krita','gimp','paint.net','photopea','pixlr','canva'];

    foreach ($common_skills as $skill) {
        if (strpos($req_text, $skill) !== false) {
            $req_skills[] = $skill;
        }
    }

    if (empty($req_skills)) {
        return !empty($candidate_arr) ? 70 : 30;
    }

    $matches = array_intersect($candidate_arr, $req_skills);
    $match_rate = count($req_skills) > 0 ? count($matches) / count($req_skills) : 0;

    return min(30 + ($match_rate * 70), 100);
}
