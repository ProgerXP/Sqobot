// initialize Underscore.string.
_.mixin(_.str.exports())

$ && $(function () {
  hookTasks()
  hookFiles()
})

function hookTasks() {
  var timer

  $(document).on('focusin focusout', '.web-tasks td.args', function (e) {
    var inputs = $('input', this)

    var empty = inputs.filter(function (i) {
      return $.trim($(this).val()) == ''
    })

    var addEmpty = false

    if (e.type == 'focusin') {
      clearTimeout(timer)
      addEmpty = e.target == empty.last()[0] || e.target == inputs.last()[0]
    } else if (inputs.length > 3 && empty.last()[0] == inputs.last()[0]) {
      timer = setTimeout(function () {
        while (empty.length && empty.last().attr('placeholder').match(/\d+$/)[0] > 3) {
          empty = empty.not( empty.last().remove() )
        }
      }, 50)
    }

    addEmpty && inputs
      .first().clone().val('')
      .attr('placeholder', function (i, str) {
        return str.replace(/\d+$/, inputs.length + 1)
      })
      .appendTo($(this).append(' '))
  })

  $(document).on('change', '.web-tasks select', function () {
    $(this).parents('form:first').find('.btn button:first')[0].focus()
  })

  $(document).on('click', '.web-tasks button', function () {
    var btn = $(this)

    if (btn.attr('value') != 'full') {
      var form = btn.parents('form:first')
      var root = btn.parents('.web-tasks:first')

      root.children('table').addClass('pivot')
      var url = form.attr('action') + '?naked=1&output=1&' + btn.attr('name') + '=1'

      root.find('td.output')
        .empty()
        .append('<p class=loading>Loading...</p>')
        .load(url, form.serialize())

      return false
    }
  })
}

function hookFiles() {
  $(document).on('click', '.web-files a.name, .web-files a.delete', function () {
    var a = $(this)

    if (a.is('.confirm')) {
      var row = a.parents('tr:first')
      var info = $.trim( row.find('td.name').text() )

      row.addClass('confirming')
      var cancel = !confirm('Really do this?\n\n' + info)
      row.removeClass('confirming')

      if (cancel) { return false }
    }

    if (a.is('.delete') || a.parents('p, tr.dir').length) {
      var old = a.parents('.web-files').find('h2').nextAll()

      $('<div>')
        .append('<p class=loading>Loading...</p>')
        .insertBefore(old[0])
        .load(a.attr('href') + '&naked=1', function () {
          old.remove()
        })

      return false
    }
  })
}
