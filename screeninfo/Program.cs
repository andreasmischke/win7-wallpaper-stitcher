using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace screeninfo
{
    class Program
    {
        static void Main(string[] args)
        {
            System.Console.Write("[");

            Screen[] screens = System.Windows.Forms.Screen.AllScreens;

            for(int i = 0, l = screens.Length; i < l; i++)
            {
                System.Console.Write("{\"BitsPerPixel\":");
                System.Console.Write(screens[i].BitsPerPixel);
                System.Console.Write(",\"Bounds\":");
                System.Console.Write(formatRect(screens[i].Bounds));
                System.Console.Write(",\"DeviceName\":\"");
                System.Console.Write(escapeSlashes(screens[i].DeviceName));
                System.Console.Write("\",\"Primary\":");
                System.Console.Write(screens[i].Primary ? "true" : "false");
                System.Console.Write(",\"WorkingArea\":");
                System.Console.Write(formatRect(screens[i].WorkingArea));
                System.Console.Write("}");
                if(i < l-1)
                {
                    System.Console.Write(",");
                }
            }

            System.Console.WriteLine("]");
        }

        static string escapeSlashes(string str)
        {
            return str.
                Replace("\\", "\\\\");
        }

        static string formatRect(Rectangle rect)
        {
            string data = "{\"X\":";
            data += rect.Left;
            data += ",\"Y\":";
            data += rect.Top;
            data += ",\"Width\":";
            data += rect.Width;
            data += ",\"Height\":";
            data += rect.Height;
            data += "}";

            return data;
        }
    }
}
