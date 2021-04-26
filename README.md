# Survey Piping Skip
Transfer data from a survey in another project to the survey being filled out, based on matching a value in a data field. The current survey can be set to auto-submit upon a data match if it is not necessary for the participant to fill out other data points. If form is not auto-submitted upon a match, data will be updated on matching data fields instantly upon clicking outside of the triggering data field.

### Explanation of module settings:
**"List of Projects to Pipe From"**- All settings in the module fall under this heading. Is repeatable for each project that will be checked for matching data.<br>
**"Project to Pipe Survey Data From"**- Dropdown of possible projects to pull data from. This can be the same project.<br>
**"Participant ID Field in Source Project"**- Field that will match a data point from the current project. Label says 'participant ID', but any field which will be a unique identifier can be used.<br>
**"Matching Participant ID Field in This Project"**- Dropdown list of fields in the current project. It is a unique identifier field that will match the data point in the other project.
**"Pipe All Data from Source Project"**- Check this if all data fields from the source project should be piped over into the current project upon a matching unique ID field. The field names must be the same in both projects and must have the same data enum and validation settings.<br>
**"Forms to Use in Piping"**- Repeating list of settings to define which forms/surveys on the source project align with a form on the current project.<br>
**"Source Form Name (from REDCap URLs)"**- The unique form name in the source project that will contain the data to be piped. It can be found in the URL when editing the form. It will usually be found after "&page=" in the browser URL.<br>
