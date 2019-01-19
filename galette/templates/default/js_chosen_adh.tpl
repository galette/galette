    var _current_page = {$members.filters->current_page};
    var _membersLoaded = function(members) {
        for (var id in members) {
            var member = members[id];
            $('#id_adh').append($('<option value="' + id + '">' + member + '</option>'));
        }
        $('#id_adh').trigger('chosen:updated');
        _chosenPages();
    }

    var _chosenPages = function(event) {
    {if $members.filters->pages > $members.filters->current_page}
        if ($('#nextpage').length == 0 && _current_page < {$members.filters->pages}) {
            if ($('#id_adh option').length < {$members.filters->show}) {
                //not enough entries
                return;
            }
            var _next = $('<li class="active-result" id="nextpage">{_T string="Load following members..."}&nbsp;<i class="fas fa-forward"></i></li>');
            _next.on('click', function (event) {
                event.preventDefault();

                var _data = {
                    page: _current_page + 1,
                };
                if ($('.chosen-search input').val() != '') {
                    _data.search = $('.chosen-search input').val();
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url : '{path_for name="contributionMembers"}',
                    data: _data,
                    {*include file="js_loader.tpl"},*}
                    success: function(res){
                        _current_page += 1;
                        _membersLoaded(res.members);
                        $('.chosen-search input').val(_data.search);
                    },
                    error: function() {
                        alert("{_T string="An error occurred retrieving members :(" escape="js"}");
                    }
                });
            });
            $('#id_adh_chosen .chosen-results').append(_next);
        }
    {/if}
    {if $members.filters->current_page > 1}
        if ($('#prevpage').length == 0) {
            var _prev = $('<li class="active-result" id="prevpage"><i class="fas fa-backward"></i>&nbsp;{_T string="Load previous members..."}</li>');
            _prev.on('click', function (event) {
                event.preventDefault();
            });
            $('#id_adh_chosen .chosen-results').prepend(_prev);
        }
    {/if}
    }

    $(function() {
        $('#id_adh').on('chosen:showing_dropdown', _chosenPages);

        $('#id_adh').chosen({
            allow_single_deselect: true,
            disable_search: false
        });

        $('#id_adh_chosen .chosen-search input').autocomplete({
            source: function( request, response ) {
                var _this = $(this);
                $.ajax({
                    type: 'POST',
                    url: '{path_for name="contributionMembers" data=["page" => 1, "search" => "PLACEBO"]}'.replace(/PLACEBO/, request.term),
                    dataType: "json",
                    success: function (res) {
                        if (res.count > 0) {
                            var _elt = $('#id_adh')
                            _elt.empty();
                            _membersLoaded(res.members);
                            _this.val(request.term);
                        }
                    }
                });
            }
        });

    });
