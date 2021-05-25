<?php
namespace LegalLogin;

use Html;
use LogFormatter;
use Message;
use MWTimestamp;
use Title;

/**
 * LogFormatter for legallogin/accept logs
 */
class LoginLogFormatter extends LogFormatter {

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		/* Current format:
		 * 1,2,3: normal LogFormatter params
		 * 4: opened policies
		 * 5: number of login since last acceptance
		 * 6: timestamp
		 * 7:
		 * 8: git [ 'sha1' => ..., 'url' => ... ]
		 */

		$yes = $this->msg( 'legallogin-yes' )->text();
		$no = $this->msg( 'legallogin-no' )->text();
		$openedPolicies = $params[3] ?? [];
		$policiesText = [];
		foreach ( $openedPolicies as $name => $value ) {
			// "$1 id: $2, required: $3, scrolled: $4, opened: $5"
			$title = Title::makeTitle( NS_MEDIAWIKI, $name );
			$revId = $value['revId'] ?? null;
			$opened = ( $value['opened'] ?? null ) === 'True' ? $yes : $no;
			$policyId = $value['policyId'] ?? '<undefined>';
			$link = $this->myPageLink( $title, $title->getText(), [ 'oldid' => $revId ] );
			$policiesText[] = $this->msg(
				'legallogin-logentry-login',
				Message::rawParam( $link ),
				$policyId,
				$opened
			)->text();
		}
		$params[3] = Message::rawParam( '<' . implode( '; ', $policiesText ) . '>' );

		$params[4] = Message::numParam( $params[4] ?? 0 );

		if ( !empty( $params[5] ) ) {
			if ( strlen( $params[5] ) === 14 ) {
				$params[5] = MWTimestamp::convert( TS_DB, $params[5] );
			}
		} else {
			$params[5] = '<unknown>';
		}

		$params[6] = $params[6] ? $yes : $no;

		$git = $params[7] ?? [];
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$gitLink = Html::element( 'a', [ 'href' => $git['url'] ?? '', 'title' => $git['sha1'] ?? '' ], 'git' );
		$params[7] = Message::rawParam( $gitLink );
		return $params;
	}

	/**
	 * @param Title|null $title
	 * @param string $text
	 * @param array $query
	 * @return string
	 */
	protected function myPageLink( ?Title $title, string $text, $query = [] ) {
		if ( !$this->plaintext ) {
			if ( !$title instanceof Title ) {
				$link = htmlspecialchars( $text );
			} else {
				$link = $this->getLinkRenderer()->makeLink( $title, $text, [], $query );
			}
		} else {
			if ( !$title instanceof Title ) {
				$link = "[[User:$text]]";
			} else {
				$link = '[[' . $title->getPrefixedText() . ']]';
			}
		}

		return $link;
	}
}
