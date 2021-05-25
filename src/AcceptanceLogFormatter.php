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
class AcceptanceLogFormatter extends LogFormatter {

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		/* Current format:
		 * 1,2,3: normal LogFormatter params
		 * 4: policies
		 * 5: questions
		 * 6: git [ 'sha1' => ..., 'url' => ... ]
		 * 7: logged [ 'count' => ..., 'timestamp' => ... ]
		 */

		$policies = $params[3] ?? [];
		$questions = $params[4] ?? [];
		$git = $params[5] ?? [];
		$logged = $params[6] ?? [];
		$params[3] = $policies ? count( $policies ) : 0;
		$yes = $this->msg( 'legallogin-yes' )->text();
		$no = $this->msg( 'legallogin-no' )->text();
		$policiesText = [];
		if ( $policies ) {
			foreach ( $policies as $p ) {
				// "$1 id: $2, required: $3, scrolled: $4, opened: $5"
				$title = Title::makeTitle( NS_MEDIAWIKI, $p['name'] );
				$link = $this->myPageLink( $title, $title->getText(), [ 'oldid' => $p['revId'] ] );
				if ( ( $p['accepted'] ?? null ) === null ) {
					$accepted = 'n/a';
				} elseif ( $p['accepted'] ) {
					$accepted = $yes;
				} else {
					$accepted = $no;
				}
				$policiesText[] = $this->msg(
					'legallogin-logentry-policy',
					Message::rawParam( $link ),
					$p['policyId'],
					$p['required'] ? $yes : $no,
					$p['scrolled'] ? $yes : $no,
					$p['opened'] ? $yes : $no,
					$accepted
				)->text();
			}
		}
		$params[4] = Message::rawParam( '<' . implode( '; ', $policiesText ) . '>' );
		$params[5] = $questions ? count( $questions ) : 0;
		$true = $this->msg( 'legallogin-true-answer-label' )->text();
		$false = $this->msg( 'legallogin-false-answer-label' )->text();
		$questionsText = [];
		if ( $questions ) {
			foreach ( $questions as $q ) {
				// "$1 answer: $2"
				$title = Title::makeTitle( NS_MEDIAWIKI, $q['name'] );
				$link = $this->myPageLink( $title, $title->getText(), [ 'oldid' => $q['revId'] ] );
				$questionsText[] = $this->msg(
					'legallogin-logentry-question',
					Message::rawParam( $link ),
					$q['answer'] ? $true : $false
				)->text();
			}
		}
		$params[6] = Message::rawParam( '<' . implode( '; ', $questionsText ) . '>' );

		$params[7] = $logged['count'] ?? 0;
		if ( !empty( $logged['timestamp'] ) ) {
			if ( strlen( $logged['timestamp'] ) === 14 ) {
				$params[8] = MWTimestamp::convert( TS_DB, $logged['timestamp'] );
			} else {
				$params[8] = $logged['timestamp'];
			}
		} else {
			$params[8] = '<unknown>';
		}

		// @phan-suppress-next-next-line SecurityCheck-DoubleEscaped
		// Calling method \Html::element() in getMessageParameters that outputs using tainted argument #2.
		$gitLink = Html::element( 'a', [ 'href' => $git['url'] ?? '', 'title' => $git['sha1'] ?? '' ], 'git' );
		$params[9] = Message::rawParam( $gitLink );
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
