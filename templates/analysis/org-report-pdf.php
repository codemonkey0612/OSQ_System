<?php
/**
 * Organizational analysis report — print-to-PDF template.
 * Rendered server-side, injected into a print window by osq-pdf.js.
 *
 * Variables provided by ajax_get_org_report_data():
 *   $company_name  string
 *   $report_date   string   (formatted)
 *   $axis_label    string   (e.g. "部署")
 *   $analysis_rows array    (group_label, respondent_count, high_stress_count, high_stress_ratio, scale_averages)
 *   $bar_chart     array    (Chart.js data structure)
 *   $radar_rows    array    (top 3 high-stress groups — each has group_label + radar chart data)
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scale_labels = array(
	'quantitative_demands' => '仕事の量',
	'qualitative_demands'  => '仕事の質',
	'physical_workload'    => '身体的負担',
	'interpersonal_stress' => '対人関係',
	'environment_stress'   => '職場環境',
	'job_control'          => '仕事の裁量',
	'skill_utilization'    => '技能の活用',
	'job_fit'              => '仕事の適性',
	'reward'               => '働きがい',
	'vigor'                => '活気',
	'irritability'         => 'イライラ感',
	'fatigue'              => '疲労感',
	'anxiety'              => '不安感',
	'depression'           => '抑うつ感',
	'physical_complaints'  => '身体愁訴',
	'supervisor_support'   => '上司支援',
	'colleague_support'    => '同僚支援',
	'family_support'       => '家族支援',
);

// Build top-3 groups by high_stress_ratio for radar charts.
$sorted = $analysis_rows;
usort( $sorted, function ( $a, $b ) {
	return $b['high_stress_ratio'] <=> $a['high_stress_ratio'];
} );
$top3 = array_slice( $sorted, 0, 3 );
?>
<div id="osq-org-report-root">

<style>
  .osq-report { font-family: "Hiragino Kaku Gothic Pro", "Meiryo", sans-serif; color: #1e293b; line-height: 1.6; max-width: 760px; margin: 0 auto; padding: 0 20px; }
  .osq-report-header { border-bottom: 3px solid #166534; padding-bottom: 16px; margin-bottom: 24px; }
  .osq-report-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 6px; color: #166534; }
  .osq-report-meta { font-size: 13px; color: #64748b; display: flex; gap: 24px; flex-wrap: wrap; }
  .osq-report-section { margin-bottom: 32px; }
  .osq-report-section h2 { font-size: 16px; font-weight: 700; color: #1e293b; border-left: 4px solid #166534; padding-left: 10px; margin-bottom: 14px; }
  .osq-report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .osq-report-table th { background: #f1f5f9; color: #475569; font-weight: 600; padding: 8px 10px; text-align: left; border: 1px solid #e2e8f0; }
  .osq-report-table td { padding: 8px 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
  .osq-report-table tr:nth-child(even) td { background: #f8fafc; }
  .osq-ratio-high { color: #dc2626; font-weight: 700; }
  .osq-ratio-normal { color: #166534; }
  .osq-chart-wrap { margin: 16px 0; }
  .osq-chart-wrap canvas { max-height: 260px; }
  .osq-radar-grid { display: flex; flex-wrap: wrap; gap: 24px; }
  .osq-radar-item { flex: 1 1 200px; min-width: 200px; }
  .osq-radar-item h3 { font-size: 13px; font-weight: 700; margin-bottom: 8px; color: #dc2626; }
  .osq-disclaimer { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; font-size: 12px; color: #64748b; line-height: 1.7; }
  .osq-disclaimer strong { color: #1e293b; }
  .osq-advice-block { page-break-inside: avoid; break-inside: avoid; }
  @media print {
    .osq-report { max-width: 100%; padding: 0; }
    .osq-chart-wrap canvas { max-height: 220px; }
    .osq-advice-block { page-break-inside: avoid; break-inside: avoid; overflow: visible !important; }
    .osq-advice-block p { overflow: visible !important; }
    .osq-report-section h2 { page-break-after: avoid; }
  }
</style>

<div class="osq-report">

  <!-- Header -->
  <div class="osq-report-header">
    <h1>組織別ストレスチェック集計レポート</h1>
    <div class="osq-report-meta">
      <span><strong>企業名：</strong><?php echo esc_html( $company_name ); ?></span>
      <span><strong>集計軸：</strong><?php echo esc_html( $axis_label ); ?></span>
      <span><strong>出力日：</strong><?php echo esc_html( $report_date ); ?></span>
    </div>
  </div>

  <!-- Summary table -->
  <div class="osq-report-section">
    <h2>グループ別集計サマリー</h2>
    <table class="osq-report-table">
      <thead>
        <tr>
          <th><?php echo esc_html( $axis_label ); ?></th>
          <th>回答者数</th>
          <th>高ストレス者数</th>
          <th>高ストレス割合</th>
          <th>上位ストレス尺度（TOP3）</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $analysis_rows as $row ) :
          $top_scales = array();
          if ( ! empty( $row['scale_averages'] ) ) {
            $avgs = $row['scale_averages'];
            arsort( $avgs );
            $top3_scales = array_slice( $avgs, 0, 3, true );
            foreach ( $top3_scales as $sk => $sv ) {
              $top_scales[] = ( $scale_labels[ $sk ] ?? $sk ) . '(' . $sv . ')';
            }
          }
          $ratio_class = $row['high_stress_ratio'] >= 10 ? 'osq-ratio-high' : 'osq-ratio-normal';
        ?>
        <tr>
          <td><?php echo esc_html( $row['group_label'] ); ?></td>
          <td><?php echo (int) $row['respondent_count']; ?></td>
          <td><?php echo (int) $row['high_stress_count']; ?></td>
          <td class="<?php echo $ratio_class; ?>"><?php echo (float) $row['high_stress_ratio']; ?>%</td>
          <td><?php echo esc_html( implode( '　', $top_scales ) ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ( empty( $analysis_rows ) ) : ?>
        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">表示できるグループがありません（最小人数条件を確認してください）</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Bar chart -->
  <?php if ( ! empty( $analysis_rows ) ) : ?>
  <div class="osq-report-section">
    <h2>高ストレス者割合 棒グラフ</h2>
    <div class="osq-chart-wrap">
      <canvas id="osq-org-bar-chart"></canvas>
    </div>
  </div>

  <!-- Radar charts for top 3 groups -->
  <?php if ( ! empty( $top3 ) ) : ?>
  <div class="osq-report-section">
    <h2>ストレス上位グループ 尺度レーダー（上位<?php echo count( $top3 ); ?>グループ）</h2>
    <div class="osq-radar-grid">
      <?php foreach ( $top3 as $i => $group ) : ?>
      <div class="osq-radar-item">
        <h3><?php echo esc_html( $group['group_label'] ); ?>（<?php echo (float) $group['high_stress_ratio']; ?>%）</h3>
        <canvas id="osq-org-radar-chart-<?php echo $i; ?>"></canvas>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- AI Advice per group (Phase 4) -->
  <?php if ( ! empty( $org_advice ) && array_filter( $org_advice ) ) : ?>
  <div class="osq-report-section osq-advice-wrap">
    <h2 style="page-break-after:avoid;">組織別AIアドバイス</h2>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px;page-break-after:avoid;">以下のアドバイスは集計データに基づきAIが生成した参考情報です。特定の原因を断定するものではありません。</p>
    <?php foreach ( $analysis_rows as $row ) :
      $advice = $org_advice[ $row['group_label'] ] ?? null;
      if ( ! $advice ) continue;
    ?>
    <div class="osq-advice-block" style="margin-bottom:20px;padding:16px;background:#f8fafc;border-left:4px solid #166534;border-radius:4px;page-break-inside:avoid;break-inside:avoid;overflow:visible;">
      <strong style="font-size:13px;color:#166534;display:block;margin-bottom:8px;page-break-after:avoid;">
        <?php echo esc_html( $row['group_label'] ); ?>
        <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px;">
          （高ストレス割合: <?php echo round( (float) $row['high_stress_ratio'], 1 ); ?>%）
        </span>
      </strong>
      <p style="font-size:13px;line-height:1.8;color:#334155;margin:0;white-space:pre-wrap;overflow:visible;"><?php echo esc_html( $advice ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Disclaimer -->
  <div class="osq-disclaimer">
    <strong>【注意事項】</strong><br>
    本レポートはストレスチェック制度に基づく組織分析結果です。個人が特定されないよう、一定人数未満のグループは表示対象外としております。本データは産業保健活動の参考資料としてのみご使用ください。外部への無断開示はお控えください。<br>
    <em>This report is for internal occupational health purposes only. Groups below the minimum respondent threshold are excluded to protect individual privacy.</em>
  </div>

</div><!-- .osq-report -->

<!-- Chart.js data — read by print window JS -->
<script id="osq-org-chart-data" type="application/json"><?php echo wp_json_encode( array(
	'bar'    => $bar_chart,
	'top3'   => array_values( array_map( function( $g ) {
		return array(
			'label'  => $g['group_label'],
			'scales' => $g['scale_averages'] ?? array(),
		);
	}, $top3 ) ),
	'scaleLabels' => $scale_labels,
) ); ?></script>

</div><!-- #osq-org-report-root -->
