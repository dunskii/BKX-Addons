<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php esc_html_e( 'PCI DSS Compliance Report', 'bkx-pci-compliance' ); ?></title>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: 0 auto; padding: 20px; }
		h1 { color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
		h2 { color: #23282d; margin-top: 30px; }
		.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
		.meta { color: #666; font-size: 14px; }
		.score-box { background: #f9f9f9; padding: 20px; border-radius: 8px; text-align: center; }
		.score { font-size: 64px; font-weight: bold; }
		.score.good { color: #46b450; }
		.score.warning { color: #ffb900; }
		.score.poor { color: #dc3232; }
		.summary { display: flex; gap: 20px; margin: 20px 0; }
		.summary-item { flex: 1; padding: 15px; background: #f0f0f1; border-radius: 4px; text-align: center; }
		.summary-item .value { font-size: 24px; font-weight: bold; }
		.summary-item.passed .value { color: #46b450; }
		.summary-item.failed .value { color: #dc3232; }
		table { width: 100%; border-collapse: collapse; margin: 20px 0; }
		th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
		th { background: #f9f9f9; }
		.badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase; }
		.badge-pass { background: #ecf7ed; color: #1e7e34; }
		.badge-fail { background: #fce4e4; color: #cc0000; }
		.badge-na { background: #f0f0f1; color: #666; }
		.badge-critical { background: #dc3232; color: #fff; }
		.badge-high { background: #ff7b00; color: #fff; }
		.badge-medium { background: #ffb900; color: #000; }
		.badge-low { background: #00a0d2; color: #fff; }
		.footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
	</style>
</head>
<body>
	<div class="header">
		<div>
			<h1><?php esc_html_e( 'PCI DSS Compliance Report', 'bkx-pci-compliance' ); ?></h1>
			<p class="meta">
				<strong><?php esc_html_e( 'Site:', 'bkx-pci-compliance' ); ?></strong> <?php echo esc_html( get_bloginfo( 'name' ) ); ?><br>
				<strong><?php esc_html_e( 'URL:', 'bkx-pci-compliance' ); ?></strong> <?php echo esc_html( home_url() ); ?><br>
				<strong><?php esc_html_e( 'Generated:', 'bkx-pci-compliance' ); ?></strong> <?php echo esc_html( wp_date( 'F j, Y g:i a' ) ); ?>
			</p>
		</div>
		<div class="score-box">
			<?php
			$score_class = 'good';
			if ( $scan['overall_score'] < 80 ) {
				$score_class = $scan['overall_score'] >= 60 ? 'warning' : 'poor';
			}
			?>
			<div class="score <?php echo esc_attr( $score_class ); ?>">
				<?php echo esc_html( round( $scan['overall_score'] ) ); ?>%
			</div>
			<div><?php esc_html_e( 'Compliance Score', 'bkx-pci-compliance' ); ?></div>
		</div>
	</div>

	<div class="summary">
		<div class="summary-item passed">
			<div class="value"><?php echo esc_html( $scan['requirements_passed'] ); ?></div>
			<div><?php esc_html_e( 'Requirements Passed', 'bkx-pci-compliance' ); ?></div>
		</div>
		<div class="summary-item failed">
			<div class="value"><?php echo esc_html( $scan['requirements_failed'] ); ?></div>
			<div><?php esc_html_e( 'Requirements Failed', 'bkx-pci-compliance' ); ?></div>
		</div>
		<div class="summary-item">
			<div class="value"><?php echo esc_html( $scan['critical_issues'] ); ?></div>
			<div><?php esc_html_e( 'Critical Issues', 'bkx-pci-compliance' ); ?></div>
		</div>
	</div>

	<?php if ( ! empty( $scan['recommendations'] ) ) : ?>
		<h2><?php esc_html_e( 'Recommendations', 'bkx-pci-compliance' ); ?></h2>
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Requirement', 'bkx-pci-compliance' ); ?></th>
					<th><?php esc_html_e( 'Issue', 'bkx-pci-compliance' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'bkx-pci-compliance' ); ?></th>
					<th><?php esc_html_e( 'Action Required', 'bkx-pci-compliance' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $scan['recommendations'] as $rec ) : ?>
					<tr>
						<td><?php echo esc_html( $rec['check_id'] ); ?></td>
						<td><?php echo esc_html( $rec['title'] ); ?></td>
						<td><span class="badge badge-<?php echo esc_attr( $rec['severity'] ); ?>"><?php echo esc_html( ucfirst( $rec['severity'] ) ); ?></span></td>
						<td><?php echo esc_html( $rec['action'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $scan['results'] ) ) : ?>
		<h2><?php esc_html_e( 'Detailed Results', 'bkx-pci-compliance' ); ?></h2>
		<?php foreach ( $scan['results'] as $req_num => $checks ) : ?>
			<h3><?php printf( esc_html__( 'Requirement %s', 'bkx-pci-compliance' ), esc_html( $req_num ) ); ?></h3>
			<table>
				<thead>
					<tr>
						<th style="width: 80px;"><?php esc_html_e( 'Check', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-pci-compliance' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Status', 'bkx-pci-compliance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $checks as $check ) : ?>
						<tr>
							<td><?php echo esc_html( $check['id'] ); ?></td>
							<td>
								<strong><?php echo esc_html( $check['title'] ); ?></strong><br>
								<small><?php echo esc_html( $check['details'] ); ?></small>
							</td>
							<td>
								<?php if ( 'pass' === $check['status'] ) : ?>
									<span class="badge badge-pass"><?php esc_html_e( 'Pass', 'bkx-pci-compliance' ); ?></span>
								<?php elseif ( 'fail' === $check['status'] ) : ?>
									<span class="badge badge-fail"><?php esc_html_e( 'Fail', 'bkx-pci-compliance' ); ?></span>
								<?php else : ?>
									<span class="badge badge-na"><?php esc_html_e( 'N/A', 'bkx-pci-compliance' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	<?php endif; ?>

	<div class="footer">
		<p>
			<?php esc_html_e( 'This report was generated by BookingX PCI DSS Compliance Tools.', 'bkx-pci-compliance' ); ?><br>
			<?php esc_html_e( 'This is a self-assessment tool and does not replace a formal PCI DSS audit by a Qualified Security Assessor (QSA).', 'bkx-pci-compliance' ); ?>
		</p>
	</div>
</body>
</html>
