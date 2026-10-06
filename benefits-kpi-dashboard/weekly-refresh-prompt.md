Refresh the market data in the Benefits Command Center dashboard (artifact https://claude.ai/artifact/Agy51spYQWmutacmiE5LdF). This is an automated weekly job. Do not change any other document and do not republish the page.

1. Read the current document with ArtifactData action "get", collection "market", doc_id "latest". Note its `version`.

2. Using the USAFacts Government Data connector, source "fred", tool "getSeriesObservations" with sort_order "desc", pull the latest 24 monthly observations (newest first, then reverse to oldest first) for:
   - CPIMEDSL with units "pc1" -> series.cpiMedical
   - CUSR0000SEMD with units "pc1" -> series.cpiHospital
   - CPIAUCSL with units "pc1" -> series.cpiAll
   - TXUR with units "lin" -> series.txUnemployment
   - BABATOTALSATX with units "lin" -> series.txBusinessApps
   - TXNA with units "pc1" -> series.txJobGrowth
   Use the month list from CPIMEDSL as `months` ("YYYY-MM"), 24 entries, oldest first. Align every other series to those months. A value of "." or a month with no observation becomes null. Round percentages to 2 decimals and counts to whole numbers.

3. Check whether newer annual figures have been published and update only if you confirm them from the publisher's own page or a reputable report:
   - KFF Employer Health Benefits Survey (benchmarks: kffYear, singlePremium, familyPremium, singleIncreasePct, familyIncreasePct, smallFirmSingle, smallFirmFamily, workerShareFamily, employerShareFamily). The survey is usually released in October.
   - IRS ACA affordability percentage for the next plan year, and HSA/HDHP limits (compliance fields). If a new year appears, add new keys in the same naming pattern (for example affordability2028) and keep the existing ones.

4. Write the document back with ArtifactData action "set", collection "market", doc_id "latest", if_version set to the version from step 1. Keep the existing `sources` array (a list of {name, url} objects) and add any new source you used. Set `updatedAt` to today's date (YYYY-MM-DD) and `refreshedBy` to "weekly routine".

5. Finish with a three-line summary: the newest month loaded, the latest medical-care and hospital-services inflation rates, and any benchmark or compliance value that changed. If a series could not be fetched, keep its previous values and name it in the summary.
